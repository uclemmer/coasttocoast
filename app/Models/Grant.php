<?php

namespace App\Models;

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use Database\Factories\GrantFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An organization's application for free or discounted registration at one
 * fair (D10).
 *
 * The applicant asks; the coordinator decides both whether to approve and what
 * the grant is worth. Nothing here is ever hard-deleted - a denial or a
 * revocation is a record, not an absence.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $requested_by
 * @property string $justification
 * @property GrantStatus $status
 * @property GrantBenefit|null $benefit_type
 * @property int|null $custom_price_cents
 * @property int|null $percent_off
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $denial_reason
 */
class Grant extends Model
{
    /** @use HasFactory<GrantFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GrantStatus::class,
            'benefit_type' => GrantBenefit::class,
            'custom_price_cents' => 'integer',
            'percent_off' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Whether a live registration has already been priced under this grant.
     *
     * A used grant cannot be revoked (doc 03, data lifecycle): the money has
     * been settled or is on its way, and moving the goalposts afterwards means
     * invoicing a school for a discount it was granted in writing.
     */
    public function isUsed(): bool
    {
        return $this->registrations()->occupying()->exists();
    }

    /**
     * A one-line description of what was granted, for emails and the portal
     * status timeline. Reads the recorded benefit rather than the resulting
     * price so the coordinator's actual decision is what the school is told.
     */
    public function benefitSummary(): ?string
    {
        return match ($this->benefit_type) {
            GrantBenefit::Free => __('Free registration'),
            GrantBenefit::CustomPrice => __('Reduced rate of :amount', [
                'amount' => '$'.number_format(((int) $this->custom_price_cents) / 100, 2),
            ]),
            GrantBenefit::PercentOff => __(':percent% off registration', ['percent' => (int) $this->percent_off]),
            null => null,
        };
    }

    /**
     * Grants that reduce a price.
     *
     * @param  Builder<Grant>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', GrantStatus::Approved);
    }

    /**
     * Applications that occupy the one-per-organization-per-event slot.
     * Withdrawn applications do not, so a school that changes its mind can
     * apply again (doc 05 card 2.6).
     *
     * @param  Builder<Grant>  $query
     */
    #[Scope]
    protected function blockingReapplication(Builder $query): void
    {
        $query->whereIn('status', GrantStatus::blockingReapplication());
    }
}
