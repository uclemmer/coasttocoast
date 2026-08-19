<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Registration;

/**
 * The seam every card payment goes through (doc 04).
 *
 * Nothing outside `App\Services\Payments` may touch the Stripe SDK, and no
 * implementation may take an amount as an argument: the figure charged is
 * always read from the registration's `price_cents` snapshot, which
 * `RegistrationService` wrote from `Event::priceFor()`. That constraint is the
 * whole reason this interface exists — it makes "the client set the price"
 * unrepresentable rather than merely discouraged (N1).
 */
interface PaymentGateway
{
    /**
     * Open a hosted checkout for this registration and record a pending
     * payment row against it.
     */
    public function createSession(Registration $registration): CheckoutSession;

    /**
     * Refund a payment in full, or partially when an amount is given.
     */
    public function refund(Payment $payment, ?int $amountCents = null): void;
}
