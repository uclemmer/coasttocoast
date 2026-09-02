<?php

namespace App\Policies;

use App\Models\EventInterest;
use App\Models\User;
use App\Support\Permissions;

/**
 * Who may read and prune the notify-me list (R2.7).
 *
 * Gated on `events.manage` rather than a permission of its own, deliberately.
 * An interest row is an attribute of a fair — it carries `event_id`, it is
 * cascade-deleted with the fair, and the only action that existed before this
 * screen is `Staff\Events\Show::announce()`, which already authorises `update`
 * on the Event. A second name for the same job would have to be granted
 * separately, and a permission granted to nobody is invisible with no error.
 *
 * Narrow this the day someone may run fairs without reading the lead list —
 * which is the same day the app grows a second staff role (see `RoleSeeder`).
 */
class EventInterestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::EVENTS_MANAGE);
    }

    public function view(User $user, EventInterest $interest): bool
    {
        return $user->can(Permissions::EVENTS_MANAGE);
    }

    /**
     * Removal is for junk: a typo'd address that bounces, or a spam signup.
     * There is no create or update — these rows come from the public form and
     * are only ever read, announced to, or thrown away.
     */
    public function delete(User $user, EventInterest $interest): bool
    {
        return $user->can(Permissions::EVENTS_MANAGE);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permissions::EVENTS_MANAGE);
    }
}
