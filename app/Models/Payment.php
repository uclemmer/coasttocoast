<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One money movement against a registration.
 *
 * The ledger, not the summary: a registration can carry an expired Checkout
 * session, then a successful one, then a refund. `registrations.status` says
 * where things stand; these rows say what happened.
 *
 * @property int $id
 * @property int $registration_id
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $stripe_checkout_session_id
 * @property string|null $stripe_payment_intent_id
 * @property string|null $check_number
 * @property Carbon|null $check_received_on
 * @property int|null $recorded_by
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'check_received_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * The coordinator who recorded a check. Null for anything Stripe did on
     * its own.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Money actually collected.
     *
     * @param  Builder<Payment>  $query
     */
    #[Scope]
    protected function succeeded(Builder $query): void
    {
        $query->where('status', PaymentStatus::Succeeded);
    }

    /**
     * The amount as dollars, for display and for PDF rendering. Storage stays
     * in integer cents everywhere (doc 02 convention 3).
     */
    public function amountInDollars(): float
    {
        return $this->amount_cents / 100;
    }
}
