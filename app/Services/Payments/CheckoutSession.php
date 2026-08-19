<?php

namespace App\Services\Payments;

/**
 * A hosted checkout session the rep is about to be sent to.
 *
 * Carries the amount so callers and tests can assert that what the gateway was
 * asked for equals the registration's `price_cents` snapshot (test-inventory
 * item 1) — the assertion that proves client input never reached the amount.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
        public int $amountCents,
    ) {}
}
