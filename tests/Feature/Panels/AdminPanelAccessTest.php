<?php

use App\Models\User;

/*
 * Card 1.1 — /admin is laravel-core's prebuilt panel, gated on `admin.access`.
 * There is no `is_admin` column anywhere in this app; if these tests ever pass
 * for a user with no role, the panel is open to the internet.
 */

it('redirects guests to the admin login page', function () {
    $this->get('/admin')->assertRedirectContains('/admin/login');
});

it('serves the admin login page', function () {
    $this->get('/admin/login')->assertOk();
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

it('answers canAccessPanel from the permission, not from a column', function () {
    expect(rep()->canAccessPanel(Filament\Facades\Filament::getPanel('core')))->toBeFalse()
        ->and(coordinator()->canAccessPanel(Filament\Facades\Filament::getPanel('core')))->toBeTrue();
});

it('lets a super admin in without the coordinator role', function () {
    $user = User::factory()->create();
    $user->assignRole(
        UClemmer\LaravelCore\Auth\Role::query()->create(['name' => config('core.auth.super_admin_role')])
    );

    $this->actingAs($user->fresh())->get('/admin')->assertOk();
});
