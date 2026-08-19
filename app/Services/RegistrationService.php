<?php

namespace App\Services;

use App\Models\Registration;

/**
 * Everything that may create, confirm or cancel a registration (doc 02
 * convention 1).
 *
 * Filament resources, the portal wizard, the Stripe webhook and the admin
 * actions all come through here. The rules it will own are the ones no model
 * can enforce on its own:
 *
 *  - no second non-cancelled registration for the same school and fair (R2.7);
 *  - the acting rep must be an ACTIVE member of that school (D9);
 *  - `price_cents` is snapshotted from `Event::priceFor()`, never from input (N1);
 *  - a price of zero confirms immediately with no payment row and no gateway;
 *  - registration is refused when the window is shut, the event is
 *    unpublished, or the room is full.
 *
 * Card 2.3 implements it. The shell exists now so cards 1.4's bindings and the
 * wizard's type hints have something real to point at.
 */
class RegistrationService
{
    /**
     * Cancel a registration, releasing its seat and its grant.
     *
     * Not a delete: once payment exists the row is an audit record (doc 03,
     * data lifecycle). Implemented in card 2.3.
     */
    public function cancel(Registration $registration, ?string $reason = null): Registration
    {
        throw new \RuntimeException('RegistrationService::cancel() lands with card 2.3.');
    }
}
