<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Livewire\Portal\CreateRegistration;
use App\Livewire\Portal\ShowRegistration as ViewRegistration;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeCheckoutService;
use Stripe\StripeClient;
use Tests\Fakes\FakePaymentGateway;

beforeEach(function () {

    $this->gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $this->gateway);

    $this->school = Organization::factory()->create();
    $this->rep = User::factory()->rep($this->school)->create();
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();

    $this->actingAs($this->rep);
});

describe('the amount handed to the gateway', function () {
    it('is the registration snapshot, never anything the wizard was given', function () {
        // Test-inventory item 1, the most important assertion in the app.
        livewire(CreateRegistration::class)
            ->set('event_id', $this->fair->id)->set('rep_name', 'Dana')->set('rep_email', 'dana@kenyon.example')->set('payment_method', PaymentMethod::Stripe->value)
            ->call('submit')
            ->assertHasNoErrors();

        $registration = Registration::query()->latest('id')->firstOrFail();

        expect($this->gateway->lastSession()?->is($registration))->toBeTrue()
            ->and($this->gateway->createSession($registration)->amountCents)->toBe(21500)
            ->and($registration->price_cents)->toBe(21500);
    });

    it('carries the grant-aware figure, not the list price', function () {
        Grant::factory()->percentOff(60)->for($this->fair)->for($this->school)->create();

        livewire(CreateRegistration::class)
            ->set('event_id', $this->fair->id)->set('rep_name', 'Dana')->set('rep_email', 'dana@kenyon.example')->set('payment_method', PaymentMethod::Stripe->value)
            ->call('submit')
            ->assertHasNoErrors();

        $registration = Registration::query()->latest('id')->firstOrFail();

        expect($registration->price_cents)->toBe(8600)
            ->and($this->gateway->createSession($registration)->amountCents)->toBe(8600);
    });

    it('never reaches the gateway for a registration a grant made free', function () {
        // Test-inventory item 1a.
        Grant::factory()->free()->for($this->fair)->for($this->school)->create();

        livewire(CreateRegistration::class)
            ->set('event_id', $this->fair->id)->set('rep_name', 'Dana')->set('rep_email', 'dana@kenyon.example')
            ->call('submit')
            ->assertHasNoErrors();

        expect($this->gateway->sessions)->toBeEmpty();
    });

    it('never reaches the gateway on the check path', function () {
        livewire(CreateRegistration::class)
            ->set('event_id', $this->fair->id)->set('rep_name', 'Dana')->set('rep_email', 'dana@kenyon.example')->set('payment_method', PaymentMethod::Check->value)
            ->call('submit')
            ->assertHasNoErrors();

        expect($this->gateway->sessions)->toBeEmpty();
    });
});

describe('the real Stripe service', function () {
    it('refuses to open a session for a registration with nothing to pay', function () {
        // Reaching here means a caller skipped the free branch, and charging
        // $0 would paper over it.
        $free = Registration::factory()->free()->forEvent($this->fair)->forOrganization($this->school)->create();

        config()->set('services.stripe.secret', 'sk_test_123');
        $this->app->forgetInstance(PaymentGateway::class);
        $service = new StripeCheckoutService(new StripeClient('sk_test_123'));

        expect(fn () => $service->createSession($free))
            ->toThrow(RuntimeException::class, 'nothing to pay');
    });

    it('refuses to refund a payment that never settled', function () {
        $registration = Registration::factory()->pendingStripe()->forEvent($this->fair)
            ->forOrganization($this->school)->create();
        $payment = Payment::factory()->pending()->for($registration)->create();

        $service = new StripeCheckoutService(new StripeClient('sk_test_123'));

        expect(fn () => $service->refund($payment))
            ->toThrow(RuntimeException::class, 'Only a settled payment');
    });

    it('refuses to refund a payment with no Stripe intent behind it', function () {
        // A mailed check is refunded by writing one back, which this
        // application cannot do.
        $registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();
        $payment = Payment::factory()->check()->for($registration)->create([
            'status' => PaymentStatus::Succeeded,
        ]);

        $service = new StripeCheckoutService(new StripeClient('sk_test_123'));

        expect(fn () => $service->refund($payment))
            ->toThrow(RuntimeException::class, 'no Stripe intent');
    });
});

describe('retrying a payment', function () {
    it('offers "pay now" while a card payment is outstanding', function () {
        // The retry path matters more than the happy one: a closed tab, a
        // declined card, a Stripe outage mid-signup.
        $pending = Registration::factory()->pendingStripe()->forEvent($this->fair)
            ->forOrganization($this->school)->create();

        expect(livewire(ViewRegistration::class, ['registration' => $pending])->instance()->canPay())->toBeTrue();
    });

    it('hides it once the money has arrived', function () {
        $confirmed = Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

        expect(livewire(ViewRegistration::class, ['registration' => $confirmed])->instance()->canPay())->toBeFalse();
    });

    it('hides it on the check path and on a free registration', function () {
        $check = Registration::factory()->pendingCheck()->forEvent($this->fair)
            ->forOrganization($this->school)->create();
        $free = Registration::factory()->free()->forEvent($this->fair)
            ->forOrganization($this->school)->create();

        expect(livewire(ViewRegistration::class, ['registration' => $check])->instance()->canPay())->toBeFalse();
        expect(livewire(ViewRegistration::class, ['registration' => $free])->instance()->canPay())->toBeFalse();
    });
});
