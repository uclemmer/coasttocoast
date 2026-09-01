<?php

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Events\RegistrationCancelled;
use App\Events\RegistrationConfirmed;
use App\Events\RegistrationCreated;
use App\Exceptions\RegistrationNotAllowed;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(RegistrationService::class);
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->organization = Organization::factory()->create();
    $this->rep = User::factory()->rep($this->organization)->create();
});

describe('membership gates', function () {
    it('lets an active rep register their own organization', function () {
        $registration = $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe);

        expect($registration->organization_id)->toBe($this->organization->id)
            ->and($registration->user_id)->toBe($this->rep->id)
            ->and($registration->status)->toBe(RegistrationStatus::PendingPayment);
    });

    it('refuses a rep whose claim is still pending', function () {
        $pending = User::factory()->pendingRep($this->organization)->create();

        expect(fn () => $this->service->create($this->fair, $this->organization, $pending, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class, 'approved representative');
    });

    it('refuses a rep who has retired', function () {
        $retired = User::factory()->retiredRep($this->organization)->create();

        expect(fn () => $this->service->create($this->fair, $this->organization, $retired, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class, 'approved representative');
    });

    it('refuses a rep registering an organization that is not theirs', function () {
        $other = Organization::factory()->create();

        expect(fn () => $this->service->create($this->fair, $other, $this->rep, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class, 'the organization your account belongs to');
    });

    it('refuses a coordinator, who has no organization at all', function () {
        $coordinator = User::factory()->coordinator()->create();

        expect(fn () => $this->service->create($this->fair, $this->organization, $coordinator, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class);
    });
});

describe('the registration window', function () {
    it('refuses a fair whose registration has closed', function () {
        $closed = Fair::factory()->registrationClosed()->create();

        expect(fn () => $this->service->create($closed, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class, 'is not open');
    });

    it('refuses a fair whose registration has not opened', function () {
        $notYet = Fair::factory()->registrationNotYetOpen()->create();

        expect(fn () => $this->service->create($notYet, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class, 'is not open');
    });

    it('refuses an unpublished fair even when the window is wide open', function () {
        // A draft fair must never take money, whatever its dates say.
        $draft = Fair::factory()->create([
            'registration_opens_at' => Carbon::now()->subWeek(),
            'registration_closes_at' => Carbon::now()->addWeek(),
        ]);

        expect(fn () => $this->service->create($draft, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class, 'is not open');
    });
});

describe('duplicates', function () {
    it('refuses a second live registration for the same organization and fair', function () {
        $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe);

        expect(fn () => $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Check))
            ->toThrow(RegistrationNotAllowed::class, 'already has a registration');
    });

    it('blocks on an awaiting-payment registration, not only a confirmed one', function () {
        // An organization with a check in the post has a place. Letting it register
        // again would produce two invoices for one table.
        Registration::factory()->pendingCheck()->forEvent($this->fair)->forOrganization($this->organization)->create();

        expect(fn () => $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class, 'already has a registration');
    });

    it('lets an organization register again after cancelling', function () {
        Registration::factory()->cancelled()->forEvent($this->fair)->forOrganization($this->organization)->create();

        expect($this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->status->toBe(RegistrationStatus::PendingPayment);
    });

    it('does not confuse two fairs or two organizations', function () {
        $otherFair = Fair::factory()->registrationOpen()->create();
        $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe);

        expect($this->service->create($otherFair, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->status->toBe(RegistrationStatus::PendingPayment);
    });
});

describe('capacity', function () {
    it('refuses once the room is full', function () {
        $small = Fair::factory()->registrationOpen()->withCapacity(1)->create();
        Registration::factory()->forEvent($small)->create();

        expect(fn () => $this->service->create($small, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class, 'is full');
    });

    it('counts a mailed check against capacity', function () {
        // Otherwise a run of pending checks oversells the venue, and every
        // oversell is an organization turned away after being told it has a place.
        $small = Fair::factory()->registrationOpen()->withCapacity(1)->create();
        Registration::factory()->pendingCheck()->forEvent($small)->create();

        expect(fn () => $this->service->create($small, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->toThrow(RegistrationNotAllowed::class, 'is full');
    });

    it('lets the coordinator enter a registration past capacity', function () {
        // A coordinator squeezing in one more table is a judgement call she is
        // entitled to make; the wizard is not.
        $small = Fair::factory()->registrationOpen()->withCapacity(1)->create();
        Registration::factory()->forEvent($small)->create();

        expect($this->service->createManualEntry($small, $this->organization, [
            'rep_name' => 'Kim Alvarado',
            'rep_email' => 'kim@example.edu',
        ], PaymentMethod::Check))->status->toBe(RegistrationStatus::PendingPayment);
    });
});

describe('the price snapshot', function () {
    it('snapshots the list price when the organization holds no grant', function () {
        expect($this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->price_cents->toBe(21500);
    });

    it('snapshots the discounted price and records the grant', function () {
        $grant = Grant::factory()->percentOff(50)->for($this->fair)->for($this->organization)->create();

        $registration = $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe);

        expect($registration->price_cents)->toBe(10750)
            ->and($registration->grant_id)->toBe($grant->id);
    });

    it('ignores a price the caller tries to supply', function () {
        // There is no argument for it, and that is the point (N1). The only
        // way to change what an organization pays is to change the fair's price or
        // approve a grant.
        $registration = $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe, [
            'rep_name' => 'Someone',
            'rep_email' => 'someone@example.edu',
        ]);

        expect($registration->price_cents)->toBe($this->fair->priceFor($this->organization));
    });

    it('does not recompute the snapshot when the fair price changes afterwards', function () {
        $registration = $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe);

        $this->fair->update(['price_cents' => 30000]);

        expect($registration->refresh()->price_cents)->toBe(21500);
    });
});

describe('the free path', function () {
    it('confirms immediately, with no payment method and no payment row', function () {
        Grant::factory()->free()->for($this->fair)->for($this->organization)->create();

        $registration = $this->service->create($this->fair, $this->organization, $this->rep);

        expect($registration->price_cents)->toBe(0)
            ->and($registration->status)->toBe(RegistrationStatus::Confirmed)
            ->and($registration->payment_method)->toBeNull()
            ->and($registration->confirmed_at)->not->toBeNull()
            ->and($registration->payments()->count())->toBe(0);
    });

    it('fires both created and confirmed, in that order', function () {
        Event::fake([RegistrationCreated::class, RegistrationConfirmed::class]);
        Grant::factory()->free()->for($this->fair)->for($this->organization)->create();

        $this->service->create($this->fair, $this->organization, $this->rep);

        Event::assertDispatched(RegistrationCreated::class);
        Event::assertDispatched(RegistrationConfirmed::class);
    });

    it('ignores a payment method offered for a free registration', function () {
        Grant::factory()->free()->for($this->fair)->for($this->organization)->create();

        expect($this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->payment_method->toBeNull();
    });

    it('demands a payment method when the registration is not free', function () {
        expect(fn () => $this->service->create($this->fair, $this->organization, $this->rep))
            ->toThrow(RegistrationNotAllowed::class, 'how you would like to pay');
    });

    it('treats a 100 percent discount as free', function () {
        Grant::factory()->percentOff(100)->for($this->fair)->for($this->organization)->create();

        expect($this->service->create($this->fair, $this->organization, $this->rep))
            ->status->toBe(RegistrationStatus::Confirmed)
            ->price_cents->toBe(0);
    });
});

describe('contact details', function () {
    it('falls back to the rep account', function () {
        $rep = User::factory()->rep($this->organization)->smsOptedIn()->create(['name' => 'Dana Whitfield']);

        expect($this->service->create($this->fair, $this->organization, $rep, PaymentMethod::Stripe))
            ->rep_name->toBe('Dana Whitfield')
            ->rep_email->toBe($rep->email)
            ->rep_phone->toBe($rep->phone);
    });

    it('accepts overrides for this fair only', function () {
        // The person staffing the table is not always the account holder.
        $registration = $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe, [
            'rep_name' => 'Jamie Okafor',
            'rep_email' => 'jokafor@example.edu',
            'rep_phone' => '+15559998888',
        ]);

        expect($registration->rep_name)->toBe('Jamie Okafor')
            ->and($registration->rep_email)->toBe('jokafor@example.edu')
            ->and($this->rep->refresh()->name)->not->toBe('Jamie Okafor');
    });
});

describe('manual entry', function () {
    it('records a registration with no account behind it', function () {
        $registration = $this->service->createManualEntry($this->fair, $this->organization, [
            'rep_name' => 'Kim Alvarado',
            'rep_email' => 'kalvarado@example.edu',
        ], PaymentMethod::Check, 'Registered by phone.');

        expect($registration->user_id)->toBeNull()
            ->and($registration->rep_name)->toBe('Kim Alvarado')
            ->and($registration->notes)->toBe('Registered by phone.');
    });

    it('still refuses a duplicate', function () {
        // The membership and window gates are about process; the duplicate
        // rule protects the data, so the coordinator does not get to skip it.
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

        expect(fn () => $this->service->createManualEntry($this->fair, $this->organization, [
            'rep_name' => 'Kim',
            'rep_email' => 'kim@example.edu',
        ], PaymentMethod::Check))->toThrow(RegistrationNotAllowed::class, 'already has a registration');
    });

    it('still snapshots the grant-aware price', function () {
        Grant::factory()->customPrice(5000)->for($this->fair)->for($this->organization)->create();

        expect($this->service->createManualEntry($this->fair, $this->organization, [
            'rep_name' => 'Kim',
            'rep_email' => 'kim@example.edu',
        ], PaymentMethod::Check))->price_cents->toBe(5000);
    });

    it('works on a fair whose registration has closed', function () {
        $closed = Fair::factory()->registrationClosed()->create();

        expect($this->service->createManualEntry($closed, $this->organization, [
            'rep_name' => 'Kim',
            'rep_email' => 'kim@example.edu',
        ], PaymentMethod::Check))->status->toBe(RegistrationStatus::PendingPayment);
    });
});

describe('confirming payment', function () {
    it('moves the registration to confirmed and stamps the time', function () {
        $registration = Registration::factory()->pendingStripe()->create();

        $this->service->confirmPayment($registration);

        expect($registration->refresh()->status)->toBe(RegistrationStatus::Confirmed)
            ->and($registration->confirmed_at)->not->toBeNull();
    });

    it('fires the confirmation event exactly once', function () {
        // Stripe redelivers a webhook until it gets a 2xx, and a second event
        // means a second receipt.
        Event::fake([RegistrationConfirmed::class]);
        $registration = Registration::factory()->pendingStripe()->create();

        $this->service->confirmPayment($registration);
        $this->service->confirmPayment($registration);

        Event::assertDispatchedTimes(RegistrationConfirmed::class, 1);
    });

    it('leaves an already-confirmed registration untouched', function () {
        $confirmedAt = Carbon::parse('2027-01-05 09:00');
        $registration = Registration::factory()->create(['confirmed_at' => $confirmedAt]);

        $this->service->confirmPayment($registration);

        expect($registration->refresh()->confirmed_at->toDateTimeString())
            ->toBe($confirmedAt->toDateTimeString());
    });
});

describe('cancelling', function () {
    it('cancels and stamps the time without deleting anything', function () {
        $registration = Registration::factory()->create();
        Payment::factory()->for($registration)->create();

        $this->service->cancel($registration, 'Organization withdrew.');

        expect($registration->refresh()->status)->toBe(RegistrationStatus::Cancelled)
            ->and($registration->cancelled_at)->not->toBeNull()
            ->and($registration->notes)->toContain('Organization withdrew.')
            // The payment history survives — this is an audit trail (N1).
            ->and($registration->payments()->count())->toBe(1)
            ->and(Registration::query()->find($registration->id))->not->toBeNull();
    });

    it('fires the cancellation event with the reason', function () {
        Event::fake([RegistrationCancelled::class]);
        $registration = Registration::factory()->create();

        $this->service->cancel($registration, 'Travel budget cut.');

        Event::assertDispatched(
            RegistrationCancelled::class,
            fn (RegistrationCancelled $e): bool => $e->reason === 'Travel budget cut.',
        );
    });

    it('refuses to cancel something already cancelled or refunded', function (string $state) {
        $registration = Registration::factory()->{$state}()->create();

        expect(fn () => $this->service->cancel($registration))
            ->toThrow(RegistrationNotAllowed::class, 'already been cancelled or refunded');
    })->with(['cancelled', 'refunded']);

    it('releases the seat it was holding', function () {
        $small = Fair::factory()->registrationOpen()->withCapacity(1)->create();
        $held = Registration::factory()->forEvent($small)->create();

        $this->service->cancel($held);

        expect($small->refresh()->isFull())->toBeFalse()
            ->and($this->service->create($small, $this->organization, $this->rep, PaymentMethod::Stripe))
            ->status->toBe(RegistrationStatus::PendingPayment);
    });

    it('releases the grant it was using', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->organization)->create();
        $registration = $this->service->create($this->fair, $this->organization, $this->rep);

        expect($grant->isUsed())->toBeTrue();

        $this->service->cancel($registration);

        expect($grant->refresh()->isUsed())->toBeFalse();
    });

    it('keeps an existing note and appends the reason beneath it', function () {
        $registration = Registration::factory()->create(['notes' => 'Prefers a corner table.']);

        $this->service->cancel($registration, 'Withdrew.');

        expect($registration->refresh()->notes)
            ->toContain('Prefers a corner table.')
            ->toContain('Withdrew.');
    });
});

describe('alreadyRegistered', function () {
    it('answers for the portal before anyone tries', function () {
        expect($this->service->alreadyRegistered($this->fair, $this->organization))->toBeFalse();

        $this->service->create($this->fair, $this->organization, $this->rep, PaymentMethod::Stripe);

        expect($this->service->alreadyRegistered($this->fair, $this->organization))->toBeTrue();
    });
});
