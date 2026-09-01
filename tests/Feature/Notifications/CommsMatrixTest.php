<?php

use App\Channels\SmsChannel;
use App\Enums\GrantBenefit;
use App\Enums\PaymentMethod;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\Admin\AdminAlert;
use App\Notifications\GrantDecided;
use App\Notifications\MembershipDecided;
use App\Notifications\PaymentReceipt;
use App\Notifications\RegistrationCheckInstructions;
use App\Services\GrantService;
use App\Services\OrganizationService;
use App\Services\Payments\CheckPaymentService;
use App\Services\RegistrationService;
use App\Services\Sms\NullSms;
use App\Services\Sms\SmsService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    config()->set('fair.alerts.enabled', true);
    config()->set('fair.alerts.email', 'coordinator@example.edu');
    config()->set('fair.alerts.phone', null);

    $this->coordinator = coordinator();
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->organization = Organization::factory()->named('Kenyon College')->create();
    $this->rep = User::factory()->rep($this->organization)->create();
});

describe('registering', function () {
    it('emails check instructions on the check path only', function () {
        app(RegistrationService::class)->create($this->fair, $this->organization, $this->rep, PaymentMethod::Check);

        Notification::assertSentOnDemand(
            RegistrationCheckInstructions::class,
            fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === $this->rep->email,
        );
    });

    it('sends no instructions to a card payer, who is already at Stripe', function () {
        app(RegistrationService::class)->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe);

        Notification::assertSentOnDemandTimes(RegistrationCheckInstructions::class, 0);
    });

    it('alerts the coordinator without texting them', function () {
        // A new registration is good news that can wait for morning. Money
        // arriving is what wakes somebody up.
        app(RegistrationService::class)->create($this->fair, $this->organization, $this->rep, PaymentMethod::Check);

        Notification::assertSentOnDemand(
            AdminAlert::class,
            fn (AdminAlert $alert): bool => str_contains($alert->subject, 'New registration')
                && $alert->smsBody === null,
        );
    });

    it('mails the fair contact, not the account holder, when they differ', function () {
        // The wizard asks who is staffing the table precisely so a
        // registration made by a director for a colleague reaches the
        // colleague.
        app(RegistrationService::class)->create($this->fair, $this->organization, $this->rep, PaymentMethod::Check, [
            'rep_name' => 'Jamie Okafor',
            'rep_email' => 'jamie@kenyon.example',
        ]);

        Notification::assertSentOnDemand(
            RegistrationCheckInstructions::class,
            fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'jamie@kenyon.example',
        );
    });
});

describe('confirming', function () {
    it('sends the receipt and texts the coordinator about the money', function () {
        config()->set('fair.alerts.phone', '+15551234567');

        $registration = Registration::factory()->pendingCheck()->forEvent($this->fair)
            ->forOrganization($this->organization)->create(['rep_email' => 'dana@kenyon.example']);

        app(CheckPaymentService::class)->markReceived($registration, $this->coordinator);

        Notification::assertSentOnDemand(
            PaymentReceipt::class,
            fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'dana@kenyon.example',
        );

        Notification::assertSentOnDemand(
            AdminAlert::class,
            fn (AdminAlert $alert): bool => str_contains($alert->subject, 'Payment received')
                && filled($alert->smsBody),
        );
    });

    it('sends exactly one receipt however many times confirmation is attempted', function () {
        // Stripe redelivers webhooks; a second receipt is what organizations notice.
        $registration = Registration::factory()->pendingStripe()->forEvent($this->fair)
            ->forOrganization($this->organization)->create();

        app(RegistrationService::class)->confirmPayment($registration);
        app(RegistrationService::class)->confirmPayment($registration);

        Notification::assertSentOnDemandTimes(PaymentReceipt::class, 1);
    });

    it('sends a receipt for a registration a grant made free', function () {
        Grant::factory()->free()->for($this->fair)->for($this->organization)->create();

        app(RegistrationService::class)->create($this->fair, $this->organization, $this->rep);

        Notification::assertSentOnDemandTimes(PaymentReceipt::class, 1);
    });
});

describe('fee assistance', function () {
    it('alerts the coordinator with the justification, so the queue is actionable from the email', function () {
        app(GrantService::class)->apply($this->fair, $this->organization, $this->rep, 'Our travel budget was cut.');

        Notification::assertSentOnDemand(
            AdminAlert::class,
            fn (AdminAlert $alert): bool => str_contains($alert->subject, 'Fee assistance request')
                && in_array('Our travel budget was cut.', $alert->rows, true),
        );
    });

    it('mails the decision to every active rep, not only the applicant', function () {
        // The applicant may have left by the time a decision lands, and a
        // grant nobody knows about is a discount nobody claims.
        $colleague = User::factory()->rep($this->organization)->create();
        $grant = app(GrantService::class)->apply($this->fair, $this->organization, $this->rep, 'Please.');

        app(GrantService::class)->approve($grant, $this->coordinator, GrantBenefit::Free);

        Notification::assertSentOnDemandTimes(GrantDecided::class, 2);
    });

    it('mails a denial too', function () {
        $grant = app(GrantService::class)->apply($this->fair, $this->organization, $this->rep, 'Please.');

        app(GrantService::class)->deny($grant, $this->coordinator, 'Funds are committed.');

        Notification::assertSentOnDemand(GrantDecided::class);
    });
});

describe('membership', function () {
    it('alerts the coordinator when somebody is waiting', function () {
        // The only thing between that person and an indefinite "awaiting
        // approval" screen.
        $newcomer = User::factory()->create();

        app(OrganizationService::class)->claim($this->organization, $newcomer);

        Notification::assertSentOnDemand(
            AdminAlert::class,
            fn (AdminAlert $alert): bool => str_contains($alert->subject, 'Approval needed'),
        );
    });

    it('passes the duplicate warning the rep pressed past into the alert', function () {
        Organization::factory()->named('The Kenyon College')->create();
        $founder = User::factory()->create();

        app(OrganizationService::class)->createWithFounder(['name' => 'Kenyon College'], $founder);

        Notification::assertSentOnDemand(
            AdminAlert::class,
            fn (AdminAlert $alert): bool => str_contains($alert->subject, 'New organization added')
                && filled($alert->rows['Possible duplicates'] ?? null),
        );
    });

    it('tells the rep when their claim is decided', function () {
        $pending = User::factory()->pendingRep($this->organization)->create();

        app(OrganizationService::class)->approveClaim($pending, $this->coordinator);

        Notification::assertSentTo($pending, MembershipDecided::class,
            fn (MembershipDecided $notification): bool => $notification->approved);
    });

    it('tells the rep when it is denied, naming the organization they asked about', function () {
        // By then the rep no longer points at it, which is why the event
        // carries the organization.
        $pending = User::factory()->pendingRep($this->organization)->create();

        app(OrganizationService::class)->denyClaim($pending, $this->coordinator, 'Not known to us.');

        Notification::assertSentTo($pending, MembershipDecided::class,
            fn (MembershipDecided $notification): bool => ! $notification->approved
                && $notification->organization?->is($this->organization) === true
                && $notification->reason === 'Not known to us.');
    });
});

describe('the alerts toggle', function () {
    it('silences everything when switched off', function () {
        // The switch for a bulk import, or a coordinator on holiday.
        config()->set('fair.alerts.enabled', false);

        app(RegistrationService::class)->create($this->fair, $this->organization, $this->rep, PaymentMethod::Check);

        Notification::assertNotSentTo(new AnonymousNotifiable, AdminAlert::class);
    });

    it('falls back to the from address rather than losing the alert', function () {
        config()->set('fair.alerts.email', null);
        config()->set('mail.from.address', 'fallback@example.edu');

        app(RegistrationService::class)->create($this->fair, $this->organization, $this->rep, PaymentMethod::Check);

        Notification::assertSentOnDemand(
            AdminAlert::class,
            fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'fallback@example.edu',
        );
    });
});

describe('SMS consent', function () {
    it('texts only a rep who opted in', function () {
        // Consent is enforced on the model, so no notification can text
        // somebody by forgetting to check (N3).
        $optedIn = User::factory()->rep($this->organization)->smsOptedIn()->create();
        $notOptedIn = User::factory()->rep($this->organization)->create(['phone' => '+15551234567']);

        expect($optedIn->routeNotificationForSms())->toBe($optedIn->phone)
            ->and($notOptedIn->routeNotificationForSms())->toBeNull();
    });

    it('sends nothing when the channel has no number to send to', function () {
        /** @var NullSms $sms */
        $sms = app(SmsService::class);
        $sms->flush();

        app(SmsChannel::class)->send(
            User::factory()->rep($this->organization)->create(['phone' => null]),
            new AdminAlert(subject: 'x', headline: 'x', smsBody: 'x'),
        );

        expect($sms->sentMessages())->toBeEmpty();
    });
});
