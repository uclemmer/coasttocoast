<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests get a migrated database. laravel-core's tables (core_roles,
| core_permissions, core_email_logs, …) are published migrations in this app,
| so RefreshDatabase brings them along with ours.
|
| RefreshDatabase migrates but seeds nothing, which leaves `core_permissions`
| empty. `Role::givePermissionTo()` resolves names against that table and
| silently drops any that are missing, so without this sync a factory that
| grants `admin.access` produces a role holding no permissions at all — and
| the panel tests fail as 403s that look like an authorization bug. Deploys
| run `core:sync-permissions`; the suite mirrors that.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Artisan::call('core:sync-permissions'))
    ->in('Feature');

/*
| The Unit suite gets the application and a database too, but NOT the
| permission sync. Model tests exercise casts, scopes and relationships, all of
| which need real tables; none of them touch a Gate, so paying for the sync on
| every one of them buys nothing. A unit test that does need permissions is a
| feature test wearing the wrong hat.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * A coordinator: verified, holding the `coordinator` role and `admin.access`.
 */
function coordinator(array $attributes = []): User
{
    return User::factory()->coordinator()->create($attributes);
}

/**
 * A plain user with a verified email — a representative, before card 3.0
 * gives them an organization.
 */
function rep(array $attributes = []): User
{
    return User::factory()->create($attributes);
}
