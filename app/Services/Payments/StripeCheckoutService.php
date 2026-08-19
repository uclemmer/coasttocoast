<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Registration;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Stripe Checkout — the hosted payment page (doc 04).
 *
 * Hosted rather than Elements on purpose: no card data ever reaches this
 * application, which keeps PCI scope at SAQ A. Do not add a custom card form.
 *
 * Card 1.4 wires the constructor and the binding; card 4.1 fills the two
 * methods in. They throw rather than returning something plausible so that a
 * half-built payment path fails loudly in development instead of silently
 * confirming a registration nobody paid for.
 */
class StripeCheckoutService implements PaymentGateway
{
    public function __construct(
        protected StripeClient $stripe,
    ) {}

    public function createSession(Registration $registration): CheckoutSession
    {
        throw new RuntimeException('StripeCheckoutService::createSession() lands with card 4.1.');
    }

    public function refund(Payment $payment, ?int $amountCents = null): void
    {
        throw new RuntimeException('StripeCheckoutService::refund() lands with card 4.3.');
    }
}
