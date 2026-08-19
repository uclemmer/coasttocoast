<?php

namespace App\Policies;

use App\Models\FaqItem;
use App\Models\User;
use App\Support\Permissions;

class FaqItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::FAQ_MANAGE);
    }

    public function view(User $user, FaqItem $faqItem): bool
    {
        return $user->can(Permissions::FAQ_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::FAQ_MANAGE);
    }

    public function update(User $user, FaqItem $faqItem): bool
    {
        return $user->can(Permissions::FAQ_MANAGE);
    }

    public function delete(User $user, FaqItem $faqItem): bool
    {
        return $user->can(Permissions::FAQ_MANAGE);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permissions::FAQ_MANAGE);
    }
}
