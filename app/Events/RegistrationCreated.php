<?php

namespace App\Events;

use App\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An organization has taken a place at a fair.
 *
 * Fired for every path — card, check and free — the moment the row exists.
 * A free registration fires this AND `RegistrationConfirmed`, in that order,
 * because it is both created and settled in the same breath.
 *
 * `RegistrationService` fires events rather than sending mail directly, so the
 * comms matrix (card 6.1) can be built without reopening the service, and so
 * the service's own tests do not depend on notification classes that do not
 * exist yet. See docs/10-implementation-decisions.md D-2.3-a.
 */
class RegistrationCreated
{
    use Dispatchable;

    public function __construct(public readonly Registration $registration) {}
}
