<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
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
 * A rep belongs to exactly one organization and carries a membership lifecycle
 * (D9): `pending` while a claim on an existing school awaits approval, `active`
 * once it holds, `retired` when they move on. Coordinators have no organization
 * at all, so a null `membership_status` means "not a representative" rather
 * than "status unknown".
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property bool $sms_opt_in
 * @property int|null $organization_id
 * @property MembershipStatus|null $membership_status
 * @property Carbon|null $membership_approved_at
 * @property Carbon|null $retired_at
 * @property int|null $retired_by
 */
#[Fillable(['name', 'email', 'password', 'phone', 'sms_opt_in'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasRoles, MustVerifyEmail
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
            'membership_status' => MembershipStatus::class,
            'membership_approved_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * The school this rep speaks for. Null for coordinators.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Registrations this person personally submitted. Not the same thing as
     * the organization's registrations — the portal dashboard shows the
     * organization's, so that a rep inherits their predecessor's work rather
     * than seeing an empty page on their first day (card 3.1).
     *
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by');
    }

    /**
     * Whether this person may act for their organization: register, apply for
     * a grant, edit the profile.
     *
     * One question, asked in one place, so services, policies and the portal
     * cannot answer it differently. A coordinator is NOT an active member of
     * anything — they administer through the admin panel and its permissions,
     * never through the rep portal.
     */
    public function actsForOrganization(): bool
    {
        return $this->organization_id !== null
            && $this->membership_status?->actsForOrganization() === true;
    }

    public function isPendingApproval(): bool
    {
        return $this->membership_status === MembershipStatus::Pending;
    }

    public function isRetired(): bool
    {
        return $this->membership_status === MembershipStatus::Retired;
    }

    /**
     * Where `SmsChannel` sends, or null.
     *
     * Consent is enforced here rather than at each call site: a number with no
     * opt-in returns null, so no notification can text somebody by forgetting
     * to check (privacy N3, decision D4).
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->sms_opt_in && filled($this->phone) ? $this->phone : null;
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

    /**
     * Reps who currently speak for a school. This is the set campaigns deliver
     * to (doc 07 §2 rule 1).
     *
     * @param  Builder<User>  $query
     */
    public function scopeActiveReps(Builder $query): void
    {
        $query->whereNotNull('organization_id')
            ->where('membership_status', MembershipStatus::Active);
    }

    /**
     * Claims waiting on a coordinator's decision — the admin queue (D9).
     *
     * @param  Builder<User>  $query
     */
    public function scopePendingReps(Builder $query): void
    {
        $query->whereNotNull('organization_id')
            ->where('membership_status', MembershipStatus::Pending);
    }
}
