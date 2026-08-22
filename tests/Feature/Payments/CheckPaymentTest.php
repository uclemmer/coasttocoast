<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Events\RegistrationConfirmed;
use App\Exceptions\RegistrationNotAllowed;
use App\Livewire\Portal\ShowRegistration as RepViewRegistration;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\Payments\CheckPaymentForm;
use App\Services\Payments\CheckPaymentService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->school = Organization::factory()->named('Kenyon College')->create();
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->registration = Registration::factory()->pendingCheck()
        ->forEvent($this->fair)->forOrganization($this->school)
        ->create(['price_cents' => 21500]);
});

describe('the printable form', function () {
    it('renders a PDF', function () {
        expect(app(CheckPaymentForm::class)->render($this->registration))->toStartWith('%PDF-');
    });

    it('is offered to the rep while the check is outstanding', function () {
        $this->actingAs(User::factory()->rep($this->school)->create());

        expect(livewire(RepViewRegistration::class, ['registration' => $this->registration])->instance()->needsCheckForm())->toBeTrue();
    });

    it('disappears once the check has arrived', function () {
        $this->actingAs(User::factory()->rep($this->school)->create());

        app(CheckPaymentService::class)->markReceived($this->registration, $this->coordinator);

        expect(livewire(RepViewRegistration::class, ['registration' => $this->registration])->instance()->needsCheckForm())->toBeFalse();
    });

    it('downloads through the portal', function () {
        $this->actingAs(User::factory()->rep($this->school)->create());

        $response = livewire(RepViewRegistration::class, ['registration' => $this->registration])
            ->call('checkForm');

        expect(downloadedContent($response))->toStartWith('%PDF-');
    });
});

describe('recording a check', function () {
    it('confirms the registration and records the payment together', function () {
        // A check marked received on a registration that stayed
        // pending_payment is the failure that gets a school turned away.
        $payment = app(CheckPaymentService::class)->markReceived(
            registration: $this->registration,
            coordinator: $this->coordinator,
            checkNumber: '1042',
        );

        expect($this->registration->refresh()->status)->toBe(RegistrationStatus::Confirmed)
            ->and($this->registration->confirmed_at)->not->toBeNull()
            ->and($payment->status)->toBe(PaymentStatus::Succeeded)
            ->and($payment->method)->toBe(PaymentMethod::Check)
            ->and($payment->check_number)->toBe('1042')
            ->and($payment->recorded_by)->toBe($this->coordinator->id);
    });

    it('defaults the amount to what was owed', function () {
        $payment = app(CheckPaymentService::class)->markReceived($this->registration, $this->coordinator);

        expect($payment->amount_cents)->toBe(21500);
    });

    it('records what actually arrived when it differs', function () {
        // A ledger of what happened, not of what should have.
        $payment = app(CheckPaymentService::class)->markReceived(
            $this->registration, $this->coordinator, amountCents: 20000,
        );

        expect($payment->amount_cents)->toBe(20000)
            ->and(app(CheckPaymentService::class)->isShort($this->registration, 20000))->toBeTrue();
    });

    it('fires the same confirmation event the webhook does, so the receipt is identical', function () {
        Event::fake([RegistrationConfirmed::class]);

        app(CheckPaymentService::class)->markReceived($this->registration, $this->coordinator);

        Event::assertDispatched(RegistrationConfirmed::class);
    });

    it('respects a grant-reduced amount', function () {
        $grant = Grant::factory()->customPrice(5000)->for($this->fair)->for($this->school)->create();
        $discounted = Registration::factory()->pendingCheck()->forEvent($this->fair)
            ->forOrganization(Organization::factory()->create())
            ->create(['price_cents' => 5000, 'grant_id' => $grant->id]);

        expect(app(CheckPaymentService::class)->markReceived($discounted, $this->coordinator)->amount_cents)
            ->toBe(5000);
    });

    it('refuses a registration that is not on the check path', function () {
        $card = Registration::factory()->pendingStripe()->forEvent($this->fair)->create();

        expect(fn () => app(CheckPaymentService::class)->markReceived($card, $this->coordinator))
            ->toThrow(RegistrationNotAllowed::class, 'not waiting on a check');
    });

    it('refuses a registration that has already been settled', function () {
        app(CheckPaymentService::class)->markReceived($this->registration, $this->coordinator);

        expect(fn () => app(CheckPaymentService::class)->markReceived($this->registration->refresh(), $this->coordinator))
            ->toThrow(RegistrationNotAllowed::class, 'not waiting on payment');
    });
});
