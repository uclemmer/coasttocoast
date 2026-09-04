<?php

use Illuminate\Support\Facades\Route;
use UClemmer\LaravelCore\Admin\Admin;
use UClemmer\LaravelCore\Admin\AdminScreen;
use UClemmer\LaravelCore\Support\AdminScreenRegistry;

/**
 * No two admin screens may claim the same URI.
 *
 * ## The defect this exists for
 *
 * Core's screen registry dedupes by NAME. Two providers can therefore register
 * different names against the same URI and both routes are created — at which
 * point Laravel's `RouteCollection`, which keys by method+URI, keeps only the
 * last, and `refreshNameLookup()` rebuilds the name list without the other. The
 * loser's route name stops existing.
 *
 * That is not quiet. The admin navigation calls `route()` on the name it was
 * handed, so a collision 500s **every** admin page with a
 * `RouteNotFoundException` naming a route nobody removed.
 *
 * ## Why it was added here, and why now
 *
 * `projects/uclemmer` hit this on 2026-09-01 taking postmaster `v0.1.3`, and
 * carries the same pair of tests. This app was left without them because it
 * contributed nothing of its own to `/admin` — its screens are at `/staff`, and
 * `core.admin.plugins` names one provider.
 *
 * That stopped being a good reason on 2026-09-04, when the postmaster upgrade
 * from `0.2` to `0.6` gave that single provider **three new admin URIs**:
 * `suppressions`, `mailing-lists` and `mailing-lists/{list}`. One provider can
 * collide with core's own screens just as easily as two providers collide with
 * each other, and the surface it can collide over just tripled without anybody
 * choosing the URIs.
 *
 * ## Why it cannot live in a package
 *
 * **No package suite can catch this, by construction.** Testbench installs one
 * package at a time, so a sibling collision is invisible to both sides — the
 * same reason `blog` and `forums` could each be correct alone and silently
 * break each other's admin on `admin/categories`. Only a host with everything
 * installed can see it.
 *
 * If this goes red after adding a package or bumping one, do not rename by
 * reflex. Decide which side owns the URI: **the screen backed by the table that
 * is actually written to should keep it.**
 */
it('registers no two admin screens against the same URI', function (): void {
    $byUri = [];

    foreach (app(AdminScreenRegistry::class)->screens() as $screen) {
        /** @var AdminScreen $screen */
        $byUri[$screen->uri][] = $screen->name;
    }

    $collisions = array_filter($byUri, fn (array $names): bool => count($names) > 1);

    expect($collisions)->toBe([], 'Two admin screens share a URI. The second silently '
        .'unregisters the first, and the navigation then 500s on the missing route name: '
        .collect($collisions)->map(fn ($n, $uri) => $uri.' => '.implode(' vs ', $n))->implode('; '));
});

/**
 * The other half, and the one that actually failed in `projects/uclemmer`. A
 * screen can be registered, survive the URI check, and still have had its route
 * name taken — so assert the names resolve rather than trusting that
 * registration implies a route.
 */
it('resolves a route for every registered admin screen', function (): void {
    $missing = [];

    foreach (app(AdminScreenRegistry::class)->screens() as $screen) {
        /** @var AdminScreen $screen */
        if (! Route::has(Admin::routeName($screen->name))) {
            $missing[] = $screen->name;
        }
    }

    expect($missing)->toBe([], 'These screens are registered but have no route: '
        .implode(', ', $missing).'. That is what a URI collision looks like from the '
        .'navigation, which calls route() on the name and 500s the whole admin.');
});
