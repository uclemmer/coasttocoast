<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use UClemmer\LaravelCore\Auth\Role;

/**
 * The fair coordinator's account.
 *
 * Behaves differently by environment on purpose. Locally it creates a known
 * account you can log straight into. Anywhere else it refuses to invent a
 * password: it reads `COORDINATOR_EMAIL` / `COORDINATOR_NAME` from the
 * environment, sets a random password nobody knows, and tells the operator to
 * send a reset. A seeder that plants a guessable admin password on a
 * production host is a back door with a changelog entry.
 */
class CoordinatorSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('fair.coordinator.email');
        $name = (string) config('fair.coordinator.name');

        $local = app()->environment(['local', 'testing']);

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->fill([
                'name' => $name,
                // Locally: a password you can type. Elsewhere: 64 random
                // characters, so the only way in is a password reset.
                'password' => Hash::make($local ? 'password' : Str::random(64)),
                'email_verified_at' => now(),
            ])->save();
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'coordinator'],
            [
                'label' => 'Fair coordinator',
                'description' => 'Runs the fair: events, registrations, grants, money and mail.',
            ],
        );

        if (! $user->hasRole('coordinator')) {
            $user->assignRole($role)->forgetCoreRoleCache();
        }

        $this->command?->info(sprintf(
            'Coordinator: %s (%s)',
            $email,
            $local ? 'password: "password"' : 'password unset — send a reset link',
        ));
    }
}
