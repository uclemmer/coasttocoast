<?php

namespace App\Models;

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Enums\RegistrationStatus;
use App\Support\Money;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One fair year.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $reception_starts_at
 * @property string $venue_name
 * @property string $venue_address
 * @property string|null $hotel_booking_url
 * @property int $price_cents
 * @property int|null $late_price_cents
 * @property Carbon|null $late_price_starts_at
 * @property int|null $capacity
 * @property Carbon|null $registration_opens_at
 * @property Carbon|null $registration_closes_at
 * @property bool $is_published
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reception_starts_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'price_cents' => 'integer',
            'late_price_cents' => 'integer',
            'late_price_starts_at' => 'datetime',
            'capacity' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Fill in a slug when one was not supplied. The admin resource offers the
     * generated value for editing; this is the safety net for seeders, imports
     * and factories.
     */
    protected static function booted(): void
    {
        static::saving(function (Event $event): void {
            if (blank($event->slug)) {
                $event->slug = Str::slug($event->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
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
     * @return HasMany<EventInterest, $this>
     */
    public function interests(): HasMany
    {
        return $this->hasMany(EventInterest::class);
    }

    /**
     * What this organization pays to attend - the single source of truth for
     * price anywhere in the app (golden rule 3, doc 03).
     *
     * The wizard display, the Stripe session amount, the check PDF and the
     * `registrations.price_cents` snapshot all come through here, so a grant
     * can never be honoured in one place and ignored in another, and no
     * client-supplied amount is ever trusted.
     *
     * Only an approved grant moves the price: pending, denied, revoked and
     * withdrawn applications are invisible to pricing (GrantStatus).
     *
     * The list price is whichever fee is current at `$at` (see
     * `listPriceAt()`). A percent-off grant discounts that fee. A custom price
     * is a fixed figure and ignores the deadline.
     */
    public function priceFor(?Organization $organization = null, ?Carbon $at = null): int
    {
        $grant = $organization ? $this->approvedGrantFor($organization) : null;
        $listPrice = $this->listPriceAt($at);

        if (! $grant instanceof Grant) {
            return $listPrice;
        }

        return match ($grant->benefit_type) {
            GrantBenefit::Free => 0,
            // A custom price is an absolute figure, not a discount, and a
            // coordinator may set it above list (an organization offering to pay more
            // is not our problem to prevent).
            GrantBenefit::CustomPrice => max(0, (int) $grant->custom_price_cents),
            // Round DOWN, so a 33%-off grant on $215.00 charges $144.05 rather
            // than $144.06. The half cent goes to the organization, not to the fair.
            GrantBenefit::PercentOff => (int) floor($listPrice * (100 - (int) $grant->percent_off) / 100),
            // An approved grant with no benefit recorded is a data fault, not a
            // free ride: charge list price and let the coordinator notice.
            null => $listPrice,
        };
    }

    /**
     * The fee before any grant: the early fee, or the late fee from
     * `late_price_starts_at` onward (docs/29).
     *
     * The late fee applies only when both late columns are set, so a fair
     * with one price is unaffected. The app runs on Chattanooga time
     * (`config/app.php`), so a cutoff of January 1 00:00 ends the early fee
     * at local midnight.
     */
    public function listPriceAt(?Carbon $at = null): int
    {
        return $this->hasLatePrice() && ! $this->isEarlyPriceAt($at)
            ? (int) $this->late_price_cents
            : $this->price_cents;
    }

    /**
     * Whether this fair has a late fee at all.
     */
    public function hasLatePrice(): bool
    {
        return $this->late_price_cents !== null && $this->late_price_starts_at !== null;
    }

    /**
     * Whether the early fee still applies at `$at` (false when the fair has
     * no late fee).
     */
    public function isEarlyPriceAt(?Carbon $at = null): bool
    {
        return $this->hasLatePrice() && ($at ?? Carbon::now())->lt($this->late_price_starts_at);
    }

    /**
     * The last day of the early fee, for copy like "through December 31".
     */
    public function earlyPriceLastDay(): ?Carbon
    {
        return $this->hasLatePrice() ? $this->late_price_starts_at->copy()->subMinute() : null;
    }

    /**
     * The fee as a visitor should read it.
     *
     * "$275" for one price, or "$275 through December 31, then $325" while
     * the early fee lasts. Once the late fee applies, the early fee is gone,
     * so it shows the late fee alone.
     */
    public function feeSummary(?Carbon $at = null): string
    {
        if (! $this->isEarlyPriceAt($at)) {
            return Money::format($this->listPriceAt($at));
        }

        return __(':early through :date, then :late', [
            'early' => Money::format($this->price_cents),
            'date' => $this->earlyPriceLastDay()->format('F j'),
            'late' => Money::format((int) $this->late_price_cents),
        ]);
    }

    /**
     * The one approved, unrevoked grant this organization holds for this event,
     * if any.
     */
    public function approvedGrantFor(Organization $organization): ?Grant
    {
        return $this->grants()
            ->where('organization_id', $organization->getKey())
            ->where('status', GrantStatus::Approved)
            ->orderByDesc('decided_at')
            ->first();
    }

    /**
     * Whether a rep can register right now.
     *
     * Unpublished events are never open - a draft fair must not accept money.
     * A null bound means no bound in that direction (R1.8): a window of
     * null/null on a published event is permanently open, which is what an
     * event created without a window should do.
     */
    public function isRegistrationOpen(?Carbon $at = null): bool
    {
        if (! $this->is_published) {
            return false;
        }

        $at ??= Carbon::now();

        if ($this->registration_opens_at && $at->lt($this->registration_opens_at)) {
            return false;
        }

        if ($this->registration_closes_at && $at->gt($this->registration_closes_at)) {
            return false;
        }

        return true;
    }

    /**
     * Registration has not opened yet - distinct from closed, because the
     * public event page shows a date notice for one and the interest form for
     * the other (R2.7, card 5.4).
     */
    public function registrationNotYetOpen(?Carbon $at = null): bool
    {
        return $this->registration_opens_at !== null
            && ($at ?? Carbon::now())->lt($this->registration_opens_at);
    }

    /**
     * Whether the room is full.
     *
     * Counts occupying registrations - confirmed plus awaiting payment -
     * rather than confirmed alone. Counting only confirmed would let a run of
     * mailed checks oversell the venue, and every one of those is an organization
     * that has to be turned away after the fact.
     */
    public function isFull(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        return $this->occupiedSeats() >= $this->capacity;
    }

    public function occupiedSeats(): int
    {
        return $this->registrations()
            ->whereIn('status', RegistrationStatus::occupying())
            ->count();
    }

    /**
     * Seats left, or null when the event is uncapped.
     */
    public function remainingCapacity(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->occupiedSeats());
    }

    /**
     * Published events, newest fair first.
     *
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Published events that have already started, most recent first.
     *
     * This is the one definition of "previous" in the app: the Last Year roster
     * (R1.4) and every cross-year campaign audience (doc 07 section 2 rule 5)
     * both read it, so they cannot drift apart.
     *
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function previousPublished(Builder $query, ?Carbon $before = null): void
    {
        $query->published()
            ->where('starts_at', '<', $before ?? Carbon::now())
            ->orderByDesc('starts_at');
    }

    /**
     * The fair the site is currently about: the next published event that has
     * not finished, falling back to the most recent past one once this year is
     * over so the site never renders an empty present.
     */
    public static function active(): ?self
    {
        return static::query()->published()->where('ends_at', '>=', Carbon::now())->orderBy('starts_at')->first()
            ?? static::query()->previousPublished()->first();
    }
}
