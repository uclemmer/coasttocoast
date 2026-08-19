<?php

namespace App\Models;

use Database\Factories\StripeWebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The idempotency ledger for Stripe webhooks (doc 04).
 *
 * Stripe redelivers until it gets a 2xx, so every handler must be safe to run
 * twice. Claiming the row first and only then doing the work is what makes the
 * second delivery a no-op instead of a second receipt.
 *
 * @property int $id
 * @property string $stripe_event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property Carbon|null $processed_at
 */
class StripeWebhookEvent extends Model
{
    /** @use HasFactory<StripeWebhookEventFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Record this delivery, or report that we have seen it before.
     *
     * Returns null when the event id is already in the ledger, which the
     * webhook controller treats as "acknowledge and do nothing". The unique
     * index on `stripe_event_id` is the actual guard - two concurrent
     * deliveries race here and exactly one wins the insert.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function claim(string $stripeEventId, string $type, array $payload): ?self
    {
        if (static::query()->where('stripe_event_id', $stripeEventId)->exists()) {
            return null;
        }

        return static::query()->create([
            'stripe_event_id' => $stripeEventId,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    public function markProcessed(): void
    {
        $this->forceFill(['processed_at' => Carbon::now()])->save();
    }
}
