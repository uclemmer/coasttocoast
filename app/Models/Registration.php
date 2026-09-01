<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One organization's place at one fair.
 *
 * Created only through `RegistrationService` (doc 02 convention 1) - the
 * duplicate rule, the membership gate, the capacity check and the price
 * snapshot all live there, and none of them can be enforced by a model that
 * anyone may `create()` directly.
 *
 * @property int $id
 * @property int $event_id
 * @property int $organization_id
 * @property int|null $user_id
 * @property RegistrationStatus $status
 * @property PaymentMethod|null $payment_method
 * @property int|null $grant_id
 * @property int $price_cents
 * @property string $rep_name
 * @property string $rep_email
 * @property string|null $rep_phone
 * @property bool $show_on_roster
 * @property string|null $notes
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 */
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'payment_method' => PaymentMethod::class,
            'price_cents' => 'integer',
            'show_on_roster' => 'boolean',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The rep who registered. Null for a coordinator's manual entry and for
     * imported history, which is why `rep_email` exists alongside it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Grant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The payment that actually settled this registration, if one did.
     */
    public function successfulPayment(): ?Payment
    {
        return $this->payments()->where('status', PaymentStatus::Succeeded)->latest('id')->first();
    }

    /**
     * A registration priced at zero by an approved grant. It has no payment
     * method and never reaches a gateway (doc 03; test inventory item 1a).
     */
    public function isFree(): bool
    {
        return $this->price_cents === 0;
    }

    /**
     * Whether this registration still holds a seat - the condition that blocks
     * a duplicate, counts against capacity, and pins its grant as used.
     */
    public function occupiesASeat(): bool
    {
        return $this->status->occupiesASeat();
    }

    /**
     * Registrations that count for anything: confirmed or awaiting payment.
     *
     * @param  Builder<Registration>  $query
     */
    #[Scope]
    protected function occupying(Builder $query): void
    {
        $query->whereIn('status', RegistrationStatus::occupying());
    }

    /**
     * Confirmed registrations only.
     *
     * @param  Builder<Registration>  $query
     */
    #[Scope]
    protected function confirmed(Builder $query): void
    {
        $query->where('status', RegistrationStatus::Confirmed);
    }

    /**
     * What the public roster shows: confirmed, and not hidden by the
     * coordinator (R1.3, R3.4). Awaiting-payment organizations are deliberately
     * absent - the roster is a promise that the organization will be there.
     *
     * @param  Builder<Registration>  $query
     */
    #[Scope]
    protected function onRoster(Builder $query): void
    {
        $query->confirmed()->where('show_on_roster', true);
    }
}
