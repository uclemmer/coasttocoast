<?php

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;
use App\Support\Permissions;

/**
 * Admin-side authorization for registrations.
 *
 * A representative's access to their own organization's registrations is a different
 * question — an active membership rather than a permission — and is answered
 * on the portal pages (card 3.1/3.3), because "may this person see this row"
 * and "may this coordinator administer every row" are not the same predicate
 * wearing different hats.
 */
class RegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::REGISTRATIONS_MANAGE);
    }

    public function view(User $user, Registration $registration): bool
    {
        return $user->can(Permissions::REGISTRATIONS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::REGISTRATIONS_MANAGE);
    }

    public function update(User $user, Registration $registration): bool
    {
        return $user->can(Permissions::REGISTRATIONS_MANAGE);
    }

    /**
     * Registrations are never deleted once payment exists (doc 03, data
     * lifecycle), and in practice payment always might. Cancel instead — it
     * releases the seat and the grant and leaves the audit trail intact.
     */
    public function delete(User $user, Registration $registration): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function cancel(User $user, Registration $registration): bool
    {
        return $user->can(Permissions::REGISTRATIONS_MANAGE)
            && $registration->status->occupiesASeat();
    }

    /**
     * Recording a check or issuing a refund is a money permission, not a
     * registrations one. The split matters the day somebody wants an assistant
     * who can manage the roster but not touch payments.
     */
    public function recordPayment(User $user, Registration $registration): bool
    {
        return $user->can(Permissions::PAYMENTS_MANAGE);
    }
}
