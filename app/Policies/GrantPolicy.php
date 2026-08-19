<?php

namespace App\Policies;

use App\Models\Grant;
use App\Models\User;
use App\Support\Permissions;

/**
 * Grant review is the coordinator's alone (R3.3b). The service enforces the
 * state machine — pending before a decision, unused before a revocation — so
 * this only answers "is this person allowed to try".
 */
class GrantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::GRANTS_MANAGE);
    }

    public function view(User $user, Grant $grant): bool
    {
        return $user->can(Permissions::GRANTS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::GRANTS_MANAGE);
    }

    public function update(User $user, Grant $grant): bool
    {
        return $user->can(Permissions::GRANTS_MANAGE);
    }

    /**
     * Grants are never hard-deleted (doc 03) — deny or revoke instead, both of
     * which leave a record of what was decided and by whom.
     */
    public function delete(User $user, Grant $grant): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
