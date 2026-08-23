<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use UClemmer\LaravelCore\Admin\Permissions as AdminPermissions;
use UClemmer\LaravelCore\Auth\Role;

/*
 * Card 1.1 — /admin is laravel-core's admin, gated on `admin.access`. There is
 * no `is_admin` column anywhere in this app; if these tests ever pass for a
 * user with no role, the admin is open to the internet.
 *
 * Ported 2026-08-22 for core 0.4. The gate moved from a Filament panel's
 * `canAccessPanel()` to route middleware (`core.permission:admin.access`), and
 * the panel's own login page went with it — there is one login page in this
 * app, at /login, the same consolidation the rep portal went through in
 * docs/12. Every rule below is the rule it was; only the URLs moved.
 */

it('redirects guests to the login page', function () {
    $this->get('/admin')->assertRedirectContains('/login');
});

it('has no admin-specific login page', function () {
    // Filament's panel shipped one at /admin/login. One app, one login page.
    $this->get('/admin/login')->assertNotFound();
});

it('forbids a signed-in user who does not hold admin.access', function () {
    $this->actingAs(rep())
        ->get('/admin')
        ->assertForbidden();
});

it('lets a coordinator into the admin panel', function () {
    $this->actingAs(coordinator())
        ->get('/admin')
        ->assertOk();
});

it('answers from the permission, not from a column', function () {
    /*
     * This asked `User::canAccessPanel()` until core 0.4. That method is gone
     * along with the interface it implemented — Filament asked the model
     * whether it could enter a panel, and there is no panel. The question it
     * was really asking is the Gate's, so the Gate is asked directly.
     */
    expect(Gate::forUser(rep())->allows(AdminPermissions::ACCESS))->toBeFalse()
        ->and(Gate::forUser(coordinator())->allows(AdminPermissions::ACCESS))->toBeTrue();
});

it('lets a super admin in without the coordinator role', function () {
    $user = User::factory()->create();
    $user->assignRole(
        Role::query()->create(['name' => config('core.auth.super_admin_role')])
    );

    $this->actingAs($user->fresh())->get('/admin')->assertOk();
});
