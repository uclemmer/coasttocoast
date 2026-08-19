<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use App\Support\Permissions;

/**
 * Campaigns are the coordinator's alone (R3.6). One permission covers writing
 * and sending, because there is nobody in this organization who would draft a
 * message for somebody else to approve.
 */
class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::MESSAGES_SEND);
    }

    public function view(User $user, Message $message): bool
    {
        return $user->can(Permissions::MESSAGES_SEND);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MESSAGES_SEND);
    }

    /**
     * A sent campaign is immutable. It is the record of what a hundred schools
     * were told, and the delivery table beneath it only means anything if the
     * message has not changed since.
     */
    public function update(User $user, Message $message): bool
    {
        return $user->can(Permissions::MESSAGES_SEND) && ! $message->isSent();
    }

    public function delete(User $user, Message $message): bool
    {
        return $user->can(Permissions::MESSAGES_SEND) && ! $message->isSent();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
