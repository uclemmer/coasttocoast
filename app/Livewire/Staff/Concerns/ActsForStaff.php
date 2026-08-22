<?php

namespace App\Livewire\Staff\Concerns;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use UClemmer\LaravelCore\Admin\Permissions as AdminPermissions;

/**
 * The gate every /staff screen shares, and the one place that knows how a
 * staff screen speaks to the user (docs/13).
 *
 * Modelled on `Portal\Concerns\ActsForAnOrganization`, but the shape of the
 * question is different. A rep's problem is *membership* — pending and retired
 * reps may browse and not act, so that trait carries a whole vocabulary of
 * explanations. Staff is binary: you administer the fair or you do not.
 *
 * TWO GATES, AND THEY ARE NOT THE SAME ONE.
 *
 *  - `abortUnlessStaff()` answers "may this person be in /staff at all", and
 *    deliberately asks `admin.access` — the exact permission
 *    `User::canAccessPanel()` uses for laravel-core's /admin panel. While both
 *    surfaces exist (see docs/13: core keeps /admin until step 4 of the
 *    workspace Filament removal) they MUST agree on who is staff, or somebody
 *    gets one and not the other and reads it as a bug.
 *  - Per-resource permission stays in `app/Policies/*` and is asked with
 *    `$this->authorize(...)`. Those policies predate this rebuild and are not
 *    Filament's — they gate on `App\Support\Permissions` constants and Laravel
 *    discovers them by name.
 *
 * WHY EVERY COMPONENT STILL CALLS `authorize()` EXPLICITLY. Filament resolved
 * policies implicitly: a resource with a policy got `viewAny`/`view`/`create`/
 * `update`/`delete` checked for free, which is why `EventPolicy`'s docblock
 * notes the resource does not re-check. Livewire does no such thing. Every
 * mount and every action authorises itself, and forgetting one is silent —
 * the screen simply works for someone who should not have it.
 */
trait ActsForStaff
{
    use AuthorizesRequests;

    public function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * Refuse anyone who is not staff, before anything renders.
     *
     * Called from `mount()`. 403 rather than a redirect: a signed-in rep who
     * guesses /staff/sponsors should be told no, not bounced to a login page
     * they are already past.
     */
    protected function abortUnlessStaff(): void
    {
        abort_unless(
            Gate::forUser($this->currentUser())->allows(AdminPermissions::ACCESS),
            403,
            __('This area is for fair staff.'),
        );
    }

    /**
     * Raise a toast through laravel-ui's live region.
     *
     * One method rather than a dispatch scattered through every action, because
     * the event name and payload shape are the package's contract and worth
     * naming in exactly one place. The staff layout renders the single
     * `<x-ui::toast />` these land in.
     *
     * Anything the user must still be able to read after clicking elsewhere —
     * a partial failure, a merge that left collisions — belongs in an
     * `x-ui::alert` on the page instead. Filament used `->persistent()` for
     * exactly those cases and a toast has no equivalent.
     */
    protected function toast(string $message, string $variant = 'success'): void
    {
        $this->dispatch('ui-toast', message: $message, variant: $variant);
    }
}
