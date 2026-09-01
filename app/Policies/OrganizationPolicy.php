<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions;

/**
 * The organization directory is coordinator territory. Reps edit their *own*
 * organization's profile through the portal, which is a different check — an active
 * membership, not a permission — and lives on the portal page rather than here
 * (card 3.1).
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::ORGANIZATIONS_MANAGE);
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can(Permissions::ORGANIZATIONS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::ORGANIZATIONS_MANAGE);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->can(Permissions::ORGANIZATIONS_MANAGE);
    }

    /**
     * An organization with any history is never deleted — the foreign keys cascade
     * and would take its registrations and grants with them. Merging is the
     * operation that removes a duplicate safely, because it repoints first.
     */
    public function delete(User $user, Organization $organization): bool
    {
        return $user->can(Permissions::ORGANIZATIONS_MANAGE)
            && ! $organization->registrations()->exists()
            && ! $organization->grants()->exists()
            && ! $organization->users()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Folding a duplicate into another organization. Separate from `delete` because
     * it is safe on an organization that has history — that is the point of it.
     */
    public function merge(User $user, Organization $organization): bool
    {
        return $user->can(Permissions::ORGANIZATIONS_MANAGE);
    }
}
