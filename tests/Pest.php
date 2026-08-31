<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests get a migrated database. laravel-core's tables (core_roles,
| core_permissions, postmaster_messages, …) are published migrations in this app,
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

/*
 * `usingAdminPanel()` and `usingSitePanel()` lived here until 2026-08-22.
 * They set Filament's current panel, because a Livewire test that mounted a
 * Filament page directly had no panel context and died with "No default
 * Filament panel is set". There are no panels — `livewire()` below mounts an
 * ordinary component and needs no ceremony.
 */

/**
 * Mount a Livewire component.
 *
 * `pestphp/pest-plugin-livewire` is not installed (it has no Pest 5 release),
 * so this is the same helper by hand. Doc 06's examples are written against
 * this name, and every component test in the suite uses it.
 *
 * @param  array<string, mixed>  $params
 */
function livewire(string $component, array $params = []): Testable
{
    return Livewire::test($component, $params);
}

/**
 * The body of a file a Livewire component handed back.
 *
 * Livewire buffers a returned `StreamedResponse` into its `download` effect,
 * base64-encoded — the stream itself is long gone by the time a test could
 * read it. `assertFileDownloaded()` checks the name; this gets at the content.
 */
function downloadedContent(Testable $response): string
{
    return base64_decode((string) data_get($response->effects, 'download.content'), strict: true) ?: '';
}

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
