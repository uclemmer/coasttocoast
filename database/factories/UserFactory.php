<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use UClemmer\LaravelCore\Admin\Permissions as AdminPermissions;
use UClemmer\LaravelCore\Auth\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => null,
            'sms_opt_in' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A fair coordinator: holds the `coordinator` role, which carries
     * `admin.access` and therefore the admin panel.
     *
     * The role and the permission are created if they are missing, so a test
     * can ask for a coordinator without seeding first. `RoleSeeder` grants the
     * role the full synced permission set; this state guarantees only the one
     * permission that opens the panel.
     */
    public function coordinator(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = Role::query()->firstOrCreate(
                ['name' => 'coordinator'],
                ['label' => 'Fair coordinator', 'description' => 'Runs the fair: events, registrations, grants, money and mail.'],
            );

            $role->givePermissionTo(AdminPermissions::ACCESS);

            $user->assignRole($role)->forgetCoreRoleCache();
        });
    }

    /**
     * A rep who has opted in to SMS and has a number to reach.
     */
    public function smsOptedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => '+1'.fake()->numerify('##########'),
            'sms_opt_in' => true,
        ]);
    }
}
