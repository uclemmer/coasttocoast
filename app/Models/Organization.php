<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A college or university that attends the fair (D8).
 *
 * The organization is the unit that registers, holds grants and appears on the
 * roster — not the person. Reps point at it, so it outlives them, and an organization
 * that changes admissions staff keeps its history, its grants and its place on
 * the win-back list.
 *
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $website
 * @property string|null $logo_path
 * @property string|null $admissions_office
 * @property string|null $admissions_email
 * @property string|null $admissions_phone
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property int|null $created_by
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Keep `normalized_name` in step with `name` automatically.
     *
     * It is derived data with two jobs — the duplicate soft-check at signup
     * (R2.7) and matching during the historical import (card 6.6) — and both
     * break quietly if a caller sets `name` without it. Deriving it on save
     * means no caller can forget.
     */
    protected static function booted(): void
    {
        static::saving(function (Organization $organization): void {
            if ($organization->isDirty('name')) {
                $organization->normalized_name = static::normalizeName($organization->name);
            }
        });
    }

    /**
     * Reduce an organization name to the form used for duplicate detection.
     *
     * Lowercase, strip punctuation, collapse whitespace, and drop a leading
     * "the" — so that "The Ohio State University", "Ohio State University" and
     * "ohio state university." all collide. Deliberately does NOT strip
     * "University" or "College": "Boston University" and "Boston College" are
     * different organizations and merging them would be worse than a missed warning.
     */
    public static function normalizeName(string $name): string
    {
        $normalized = Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();

        return str_starts_with($normalized, 'the ')
            ? substr($normalized, 4)
            : $normalized;
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The reps who currently speak for this organization. This is the set campaigns
     * deliver to (doc 07 §2 rule 1) and the set allowed to register or apply
     * for a grant.
     *
     * @return HasMany<User, $this>
     */
    public function activeReps(): HasMany
    {
        return $this->users()->where('membership_status', MembershipStatus::Active);
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return HasMany<Grant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(Grant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Other organizations whose normalized name matches this one — the soft
     * duplicate check. Returns a query, not a boolean, because the signup flow
     * shows the coordinator *which* organizations it might be rather than merely
     * that a collision exists.
     *
     * @return Builder<Organization>
     */
    public function possibleDuplicates(): Builder
    {
        return static::query()
            ->where('normalized_name', static::normalizeName($this->name ?? ''))
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()));
    }

    /**
     * Organizations whose name normalizes to the same thing as the given one.
     *
     * @param  Builder<Organization>  $query
     */
    #[Scope]
    protected function matchingName(Builder $query, string $name): void
    {
        $query->where('normalized_name', static::normalizeName($name));
    }

    /**
     * The address as it belongs on a receipt or a W-9, or null when the organization
     * has not filled one in.
     */
    public function formattedAddress(): ?string
    {
        $lines = array_filter([
            $this->address_line1,
            $this->address_line2,
            trim(implode(' ', array_filter([
                $this->city ? $this->city.',' : null,
                $this->state,
                $this->postal_code,
            ]))) ?: null,
        ]);

        return $lines === [] ? null : implode("\n", $lines);
    }
}
