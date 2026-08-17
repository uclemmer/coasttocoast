<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;
use UClemmer\LaravelCore\Admin\Permissions as AdminPermissions;
use UClemmer\LaravelCore\Auth\HasCoreRoles;
use UClemmer\LaravelCore\Support\Contracts\HasRoles;

/**
 * A person with an account. Two kinds, distinguished by role and by
 * organization membership rather than by a boolean column:
 *
 *  - coordinators — hold the `coordinator` role, which carries `admin.access`
 *    and reach the admin panel at /admin;
 *  - representatives — belong to an organization and reach the rep portal at
 *    /portal once their email is verified.
 *
 * Roles and permissions come from uclemmer/laravel-core (`HasCoreRoles`).
 * There is deliberately no `is_admin` column — see docs/01-requirements.md D6.
 *
 * Organization membership columns (organization_id, membership_status,
 * retired_at/retired_by) arrive with card 1.2.
 */
#[Fillable(['name', 'email', 'password', 'phone', 'sms_opt_in'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasRoles, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasCoreRoles, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'sms_opt_in' => 'boolean',
        ];
    }

    /**
     * Panel access.
     *
     * This is deliberately NOT laravel-core's `CanAccessCorePanel` trait: that
     * trait answers for every panel, and this app has two. The `core` branch is
     * the trait's own check, kept identical on purpose so the admin panel keeps
     * following `admin.access` (super admins pass via the Gate's before hook).
     *
     * Deviation from card 1.1 recorded in docs/05-build-roadmap.md.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'core' => Gate::forUser($this)->allows(AdminPermissions::ACCESS),
            'rep' => $this->hasVerifiedEmail(),
            default => false,
        };
    }

    /**
     * Users who have opted in to SMS and have a number to send to.
     *
     * @param  Builder<User>  $query
     */
    public function scopeSmsReachable(Builder $query): void
    {
        $query->where('sms_opt_in', true)->whereNotNull('phone');
    }
}
