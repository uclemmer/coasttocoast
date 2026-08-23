<?php

use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use UClemmer\LaravelCore\Admin\Admin;
use UClemmer\LaravelCore\Auth\Permission;
use UClemmer\LaravelCore\Auth\Role;

/*
 * Card 1.1 — the wiring between this app and uclemmer/laravel-core. These are
 * the assertions that fail loudly when a config key is renamed or a publish
 * step is skipped, rather than quietly removing a feature from the panel.
 */

/*
 * No panels at all now. The rep portal was Filament's until 2026-08-21 and is
 * Livewire (docs/12); /admin was core's Filament panel until core 0.4, and is
 * core's Livewire admin as of 2026-08-22. The claims below are the same ones
 * the panel assertions made — where the admin lives, what this app contributes
 * to it, and whose name is on it — asked of the thing that replaced it.
 */
it('mounts core\'s admin at /admin', function () {
    expect(Admin::enabled())->toBeTrue()
        ->and(Admin::path())->toBe('admin')
        ->and(Admin::url('dashboard'))->toEndWith('/admin');
});

it('contributes no screens of its own, and gets core\'s', function () {
    /*
     * Inverted 2026-08-21 when the fair's resources left for /staff (docs/13),
     * and it stays inverted: `core.admin.plugins` is the same config key it was
     * under Filament, holding `ProvidesAdminScreens` class-strings now. This
     * app names none — everything at /admin is core's own.
     */
    expect(config('core.admin.plugins'))->toBe([])
        ->and(Admin::has('users.index'))->toBeTrue()
        ->and(Admin::has('email-log.index'))->toBeTrue();
});

it('brands the admin for the fair', function () {
    // Read by core's admin layout rather than by a panel builder; same key.
    expect(config('core.admin.brand'))->toBe('Coast to Coast College Fair');
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
