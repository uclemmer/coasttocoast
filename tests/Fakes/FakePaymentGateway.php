<?php

namespace Tests\Fakes;

use App\Models\Payment;
use App\Models\Registration;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\PaymentGateway;

/**
 * The gateway every payment test binds in place of Stripe.
 *
 * It records what it was asked to charge, which is the whole point: the most
 * important assertion in this application is that the amount handed to the
 * gateway equals the registration's `price_cents` snapshot and never anything
 * a client sent (test-inventory item 1).
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<int, Registration> */
    public array $sessions = [];

    /** @var array<int, array{payment: Payment, amount_cents: int|null}> */
    public array $refunds = [];

    public function createSession(Registration $registration): CheckoutSession
    {
        $this->sessions[] = $registration;

        return new CheckoutSession(
            id: 'cs_fake_'.$registration->getKey(),
            url: 'https://checkout.stripe.test/session/'.$registration->getKey(),
            // Read from the snapshot, exactly as the real service must.
            amountCents: $registration->price_cents,
        );
    }

    public function refund(Payment $payment, ?int $amountCents = null): void
    {
        $this->refunds[] = ['payment' => $payment, 'amount_cents' => $amountCents];
    }

    public function lastSession(): ?Registration
    {
        return $this->sessions === [] ? null : $this->sessions[array_key_last($this->sessions)];
    }
}
