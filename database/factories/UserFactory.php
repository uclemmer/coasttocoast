<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use UClemmer\LaravelCore\Admin\Permissions as AdminPermissions;
use UClemmer\LaravelCore\Auth\Permission;
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
     * The role is created if it is missing, so a test can ask for a
     * coordinator without seeding first, and it is granted **every synced
     * permission** — exactly what `RoleSeeder` does, because there is one fair
     * coordinator and she runs all of it.
     *
     * Granting only `admin.access` here (as this state did until 2026-08-18)
     * made every admin resource test fail as a 403 that looked like a policy
     * bug, because the app's own permissions were never on the role. The suite
     * runs `core:sync-permissions` before each feature test, so the table this
     * reads is populated by then. `admin.access` is still granted by name as a
     * belt-and-braces fallback for the unit suite, which does not sync.
     */
    public function coordinator(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = Role::query()->firstOrCreate(
                ['name' => 'coordinator'],
                ['label' => 'Fair coordinator', 'description' => 'Runs the fair: events, registrations, grants, money and mail.'],
            );

            $role->givePermissionTo(AdminPermissions::ACCESS);
            $role->permissions()->syncWithoutDetaching(Permission::query()->pluck('id')->all());

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

    /**
     * An active representative of an organization (card 1.2, D9).
     *
     * Pass an organization to put several reps in the same one; omit it and
     * the factory makes an organization for this rep alone.
     */
    public function rep(?Organization $organization = null): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization?->getKey() ?? Organization::factory(),
            'membership_status' => MembershipStatus::Active,
            'membership_approved_at' => Carbon::now(),
            'retired_at' => null,
            'retired_by' => null,
        ]);
    }

    /**
     * A rep whose claim on an existing organization is still waiting on a
     * coordinator. Can log in and browse, can do nothing on the org's behalf.
     */
    public function pendingRep(?Organization $organization = null): static
    {
        return $this->rep($organization)->state(fn (array $attributes) => [
            'membership_status' => MembershipStatus::Pending,
            'membership_approved_at' => null,
        ]);
    }

    /**
     * A rep who has moved on. Keeps the account and the history, loses every
     * org right, and is excluded from campaign audiences (R2.10, doc 07 §2).
     */
    public function retiredRep(?Organization $organization = null): static
    {
        return $this->rep($organization)->state(fn (array $attributes) => [
            'membership_status' => MembershipStatus::Retired,
            'retired_at' => Carbon::now(),
        ]);
    }
}
