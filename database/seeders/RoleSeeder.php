<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use UClemmer\LaravelCore\Auth\Permission;
use UClemmer\LaravelCore\Auth\Role;

/**
 * The `coordinator` role — the only role this app defines.
 *
 * Permissions are declared in code (laravel-core's own `Permissions` classes
 * plus `App\Support\Permissions`) and upserted by `core:sync-permissions`, which
 * this seeder runs first so a fresh database ends up with rows to grant.
 *
 * The coordinator holds EVERY synced permission: there is one fair coordinator
 * and she runs all of it. Narrow this the day a second kind of staff account
 * exists — that is the point at which a role split means something.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('core:sync-permissions');

        $role = Role::query()->firstOrCreate(
            ['name' => 'coordinator'],
            [
                'label' => 'Fair coordinator',
                'description' => 'Runs the fair: events, registrations, grants, money and mail.',
            ],
        );

        $role->permissions()->sync(Permission::query()->pluck('id')->all());
    }
}
