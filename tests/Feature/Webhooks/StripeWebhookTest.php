<?php

use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Events\RegistrationConfirmed;
use App\Models\Event as Fair;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->organization = Organization::factory()->create();
    $this->registration = Registration::factory()->pendingStripe()
        ->forEvent($this->fair)->forOrganization($this->organization)
        ->create(['price_cents' => 21500]);
    $this->payment = Payment::factory()->pending()->for($this->registration)->create([
        'amount_cents' => 21500,
        'stripe_checkout_session_id' => 'cs_test_abc',
    ]);
});

/**
 * A signed delivery, exactly as doc 06 documents it.
 *
 * @param  array<string, mixed>  $payload
 */
function stripeSignedPost(array $payload): TestResponse
{
    $json = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$json}", config('services.stripe.webhook_secret'));

    return test()->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $json);
}

/**
 * @param  array<string, mixed>  $object
 * @return array<string, mixed>
 */
function stripeEvent(string $type, array $object, string $id = 'evt_test_1'): array
{
    return ['id' => $id, 'type' => $type, 'data' => ['object' => $object]];
}

describe('signature verification', function () {
    it('rejects an unsigned delivery and changes nothing', function () {
        // Test-inventory item 2. Without this, anyone who can reach the URL
        // can confirm a registration.
        $this->postJson('/webhooks/stripe', stripeEvent('checkout.session.completed', []))
            ->assertStatus(400);

        expect($this->registration->refresh()->status)->toBe(RegistrationStatus::PendingPayment)
            ->and(StripeWebhookEvent::query()->count())->toBe(0);
    });

    it('rejects a mis-signed delivery', function () {
        $json = json_encode(stripeEvent('checkout.session.completed', []));

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], $json)->assertStatus(400);

        expect($this->registration->refresh()->status)->toBe(RegistrationStatus::PendingPayment);
    });

    it('refuses every delivery when no secret is configured', function () {
        // Degrading would mean treating every caller as Stripe.
        config()->set('services.stripe.webhook_secret', null);

        $this->postJson('/webhooks/stripe', stripeEvent('checkout.session.completed', []))
            ->assertStatus(500);
    });

    it('accepts a correctly signed delivery', function () {
        stripeSignedPost(stripeEvent('checkout.session.completed', [
            'id' => 'cs_test_abc',
            'amount_total' => 21500,
            'payment_intent' => 'pi_test_abc',
        ]))->assertOk();
    });
});

describe('checkout.session.completed', function () {
    it('confirms the registration and settles the payment', function () {
        stripeSignedPost(stripeEvent('checkout.session.completed', [
            'id' => 'cs_test_abc',
            'amount_total' => 21500,
            'payment_intent' => 'pi_test_abc',
        ]))->assertOk();

        expect($this->registration->refresh()->status)->toBe(RegistrationStatus::Confirmed)
            ->and($this->registration->confirmed_at)->not->toBeNull()
            ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Succeeded)
            ->and($this->payment->stripe_payment_intent_id)->toBe('pi_test_abc');
    });

    it('is idempotent, so a redelivery cannot send a second receipt', function () {
        // Test-inventory item 3. Stripe retries until it gets a 2xx.
        Event::fake([RegistrationConfirmed::class]);

        $payload = stripeEvent('checkout.session.completed', [
            'id' => 'cs_test_abc',
            'amount_total' => 21500,
            'payment_intent' => 'pi_test_abc',
        ]);

        stripeSignedPost($payload)->assertOk();
        stripeSignedPost($payload)->assertOk();

        Event::assertDispatchedTimes(RegistrationConfirmed::class, 1);
        expect(StripeWebhookEvent::query()->count())->toBe(1);
    });

    it('flags an amount mismatch and refuses to confirm', function () {
        // Test-inventory item 5. The only ways here are a tampered session or
        // a bug in our own pricing, and both mean the figure the organization agreed
        // to and the figure that moved are different.
        stripeSignedPost(stripeEvent('checkout.session.completed', [
            'id' => 'cs_test_abc',
            'amount_total' => 10000,
            'payment_intent' => 'pi_test_abc',
        ]))->assertOk();

        expect($this->registration->refresh()->status)->toBe(RegistrationStatus::PendingPayment)
            ->and($this->registration->notes)->toContain('PAYMENT MISMATCH')
            ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Failed);
    });

    it('finds the registration through metadata when no payment row matches', function () {
        $orphan = Registration::factory()->pendingStripe()->forEvent($this->fair)
            ->create(['price_cents' => 21500]);

        stripeSignedPost(stripeEvent('checkout.session.completed', [
            'id' => 'cs_test_unknown',
            'amount_total' => 21500,
            'metadata' => ['registration_id' => (string) $orphan->id],
        ]))->assertOk();

        expect($orphan->refresh()->status)->toBe(RegistrationStatus::Confirmed);
    });

    it('acknowledges an unrecognised session without failing', function () {
        // A 500 here makes Stripe retry for three days.
        stripeSignedPost(stripeEvent('checkout.session.completed', [
            'id' => 'cs_test_nothing',
            'amount_total' => 21500,
        ]))->assertOk();
    });
});

describe('checkout.session.expired', function () {
    it('fails the attempt but leaves the place held', function () {
        // The organization still has its seat and the "pay now" button still works.
        // Only the attempt failed.
        stripeSignedPost(stripeEvent('checkout.session.expired', ['id' => 'cs_test_abc']))->assertOk();

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Failed)
            ->and($this->registration->refresh()->status)->toBe(RegistrationStatus::PendingPayment);
    });
});

describe('charge.refunded', function () {
    beforeEach(function () {
        $this->payment->forceFill([
            'status' => PaymentStatus::Succeeded,
            'stripe_payment_intent_id' => 'pi_test_abc',
        ])->save();

        $this->registration->forceFill([
            'status' => RegistrationStatus::Confirmed,
            'confirmed_at' => now(),
        ])->save();
    });

    it('refunds the registration and drops it off the roster', function () {
        stripeSignedPost(stripeEvent('charge.refunded', [
            'payment_intent' => 'pi_test_abc',
            'amount_refunded' => 21500,
        ]))->assertOk();

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Refunded)
            ->and($this->registration->refresh()->status)->toBe(RegistrationStatus::Refunded)
            // A refunded registration is one that is not attending.
            ->and($this->registration->show_on_roster)->toBeFalse();
    });

    it('leaves a partial refund attending', function () {
        // The organization is still coming; it just paid less.
        stripeSignedPost(stripeEvent('charge.refunded', [
            'payment_intent' => 'pi_test_abc',
            'amount_refunded' => 5000,
        ]))->assertOk();

        expect($this->registration->refresh()->status)->toBe(RegistrationStatus::Confirmed)
            ->and($this->registration->show_on_roster)->toBeTrue()
            ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Succeeded);
    });

    it('acknowledges a refund for a payment it does not know', function () {
        stripeSignedPost(stripeEvent('charge.refunded', [
            'payment_intent' => 'pi_test_stranger',
            'amount_refunded' => 21500,
        ]))->assertOk();
    });
});

describe('unhandled event types', function () {
    it('records them in the ledger and does nothing else', function () {
        stripeSignedPost(stripeEvent('customer.created', ['id' => 'cus_test']))->assertOk();

        expect(StripeWebhookEvent::query()->where('type', 'customer.created')->exists())->toBeTrue()
            ->and($this->registration->refresh()->status)->toBe(RegistrationStatus::PendingPayment);
    });
});
