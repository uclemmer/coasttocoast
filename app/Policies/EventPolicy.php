<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Support\Permissions;

/**
 * Events are coordinator territory (R3.1). One permission covers the lot,
 * because there is no meaningful split between "may read the fair calendar"
 * and "may change it" — anyone with the admin panel has both.
 *
 * Filament resolves this automatically for the resource's pages and actions;
 * the resource does not re-check it. Public pages read published events
 * without going through a policy at all, which is correct — the roster is
 * public by design.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::EVENTS_MANAGE);
    }

    public function view(User $user, Event $event): bool
    {
        return $user->can(Permissions::EVENTS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::EVENTS_MANAGE);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can(Permissions::EVENTS_MANAGE);
    }

    /**
     * Deleting a fair that has registrations against it would take real
     * financial history with it — the foreign keys cascade. Once anyone has
     * registered, the event is a permanent record; unpublish it instead.
     */
    public function delete(User $user, Event $event): bool
    {
        return $user->can(Permissions::EVENTS_MANAGE)
            && ! $event->registrations()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
