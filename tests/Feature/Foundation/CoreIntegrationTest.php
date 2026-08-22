<?php

use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use UClemmer\LaravelCore\Auth\Permission;
use UClemmer\LaravelCore\Auth\Role;

/*
 * Card 1.1 — the wiring between this app and uclemmer/laravel-core. These are
 * the assertions that fail loudly when a config key is renamed or a publish
 * step is skipped, rather than quietly removing a feature from the panel.
 */

/*
 * One panel now. The rep portal was Filament's until 2026-08-21 and is
 * Livewire; /admin is the only panel left, and it goes too when core is
 * decoupled. See docs/12.
 */
it('registers the admin panel, and only that one', function () {
    expect(Filament::getPanel('core')->getPath())->toBe('admin')
        ->and(collect(Filament::getPanels())->keys()->all())->toBe(['core']);
});

it('no longer attaches a fair plugin, and keeps core\'s own', function () {
    /*
     * Inverted 2026-08-21. The fair's resources left Filament for /staff
     * (docs/13), so `FairPlugin` and its `core.admin.plugins` entry are gone.
     * Core's panel stays until step 4 of the workspace removal, which is what
     * the second half asserts.
     */
    expect(Filament::getPanel('core')->hasPlugin('fair'))->toBeFalse()
        ->and(Filament::getPanel('core')->hasPlugin('laravel-core'))->toBeTrue();
});

it('brands the admin panel for the fair', function () {
    expect(Filament::getPanel('core')->getBrandName())->toBe('Coast to Coast College Fair');
});

it('published the core migrations', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with(['core_roles', 'core_permissions', 'core_role_user', 'core_permission_role', 'core_email_logs', 'core_contact_submissions', 'core_contents']);

it('syncs the application permissions alongside the package ones', function () {
    Artisan::call('core:sync-permissions');

    $names = Permission::query()->pluck('name');

    expect($names)->toContain('admin.access')
        ->and($names)->toContain(Permissions::EVENTS_MANAGE)
        ->and($names)->toContain(Permissions::GRANTS_MANAGE)
        ->and($names)->toContain(Permissions::MESSAGES_SEND);
});

it('seeds a coordinator role holding every synced permission', function () {
    $this->seed(RoleSeeder::class);

    $role = Role::query()->where('name', 'coordinator')->firstOrFail();

    expect($role->permissions()->count())
        ->toBe(Permission::query()->count())
        ->and($role->permissions()->where('name', 'admin.access')->exists())->toBeTrue();
});

it('passes core:doctor', function () {
    $this->seed(RoleSeeder::class);
    coordinator();

    expect(Artisan::call('core:doctor'))->toBe(0, Artisan::output());
});
