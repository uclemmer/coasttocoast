<?php

namespace App\Policies;

use App\Models\Sponsor;
use App\Models\User;
use App\Support\Permissions;

class SponsorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::SPONSORS_MANAGE);
    }

    public function view(User $user, Sponsor $sponsor): bool
    {
        return $user->can(Permissions::SPONSORS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::SPONSORS_MANAGE);
    }

    public function update(User $user, Sponsor $sponsor): bool
    {
        return $user->can(Permissions::SPONSORS_MANAGE);
    }

    public function delete(User $user, Sponsor $sponsor): bool
    {
        return $user->can(Permissions::SPONSORS_MANAGE);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permissions::SPONSORS_MANAGE);
    }
}
