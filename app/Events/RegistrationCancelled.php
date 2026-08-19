<?php

namespace App\Events;

use App\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A registration was withdrawn. The row survives — cancelling releases the
 * seat and the grant, it does not delete the history (doc 03, data lifecycle).
 */
class RegistrationCancelled
{
    use Dispatchable;

    public function __construct(
        public readonly Registration $registration,
        public readonly ?string $reason = null,
    ) {}
}
