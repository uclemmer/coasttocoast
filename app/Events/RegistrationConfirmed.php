<?php

namespace App\Events;

use App\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A registration is settled: the money arrived, or a grant made it free.
 *
 * This is the receipt trigger. Fired exactly once per registration —
 * `confirmPayment()` is idempotent, because Stripe redelivers webhooks and a
 * second receipt for the same registration is the kind of thing organizations notice.
 */
class RegistrationConfirmed
{
    use Dispatchable;

    public function __construct(public readonly Registration $registration) {}
}
