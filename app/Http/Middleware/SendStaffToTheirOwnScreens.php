<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use UClemmer\LaravelCore\Admin\Permissions as AdminPermissions;

/**
 * Bounce a staff member with no organization out of the rep portal and into `/staff`.
 *
 * **The bug this fixes.** `config/core.php` sets
 * `core.auth.routes.redirect_to => '/portal'` and its comment says "reps land in
 * the portal" — but core's `LoginController::redirectTo()` reads that as a
 * single string for *everybody*. The coordinator therefore signed in and landed
 * on a portal dashboard reading "Your account is not attached to an organization.
 * Contact the fair coordinator to be added", with no link anywhere on the page
 * to `/staff` or `/admin`. They are the fair coordinator, and the only way to
 * their own screens was typing the URL. Found in a browser pass (docs/10,
 * D-9-d); no test caught it, because every test navigates straight to the
 * screen it is testing.
 *
 * **Here rather than in the login flow.** Core owns the login controller and
 * takes one string, so fixing it there means a package change and a release.
 * Guarding the destination instead catches every route in — the post-login
 * redirect, the `redirectUsersTo('/portal')` that sends an authenticated user
 * away from `/login`, and a stale bookmark — in one place.
 *
 * **Deliberately narrow: staff AND no organization.** A rep genuinely waiting
 * to be attached to an organization must still get that message, because for them it
 * is true and actionable. And a staff member who *does* have an organization has real
 * business in the portal, so they are left alone. The gate is
 * `AdminPermissions::ACCESS` — the same permission `ActsForStaff` and
 * `User::canAccessPanel()` ask, so the three cannot drift about who is staff.
 */
class SendStaffToTheirOwnScreens
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user !== null
            && $user->organization_id === null
            && Gate::forUser($user)->allows(AdminPermissions::ACCESS)
        ) {
            return redirect()->route('staff.dashboard');
        }

        return $next($request);
    }
}
