<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

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
function coordinator(array $attributes = []): App\Models\User
{
    return App\Models\User::factory()->coordinator()->create($attributes);
}

/**
 * A plain user with a verified email — a representative, before card 3.0
 * gives them an organization.
 */
function rep(array $attributes = []): App\Models\User
{
    return App\Models\User::factory()->create($attributes);
}
