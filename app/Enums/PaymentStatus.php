<?php

namespace App\Enums;

/**
 * The state of one money movement.
 *
 * For Stripe this mirrors what the webhook told us — the webhook is the source
 * of truth (golden rule 3), never the browser returning from Checkout. For
 * checks it is set by the coordinator's "mark check received" action.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Succeeded => __('Succeeded'),
            self::Failed => __('Failed'),
            self::Refunded => __('Refunded'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Succeeded => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }
}
