<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The lifecycle of one organization's place at one fair.
 *
 * Registrations are never hard-deleted once money is involved (doc 03, data
 * lifecycle) — they move to `Cancelled` or `Refunded` so the audit trail
 * survives.
 */
enum RegistrationStatus: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingPayment => __('Awaiting payment'),
            self::Confirmed => __('Confirmed'),
            self::Cancelled => __('Cancelled'),
            self::Refunded => __('Refunded'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Confirmed => 'success',
            self::Cancelled => 'gray',
            self::Refunded => 'danger',
        };
    }

    /**
     * Statuses that still hold a seat at the fair.
     *
     * This is the set that blocks a duplicate registration (R2.7), counts
     * against `Event::capacity` (`isFull()`), and pins a grant as "used" so it
     * can no longer be revoked. Cancelled and refunded registrations release
     * all three.
     *
     * @return array<int, self>
     */
    public static function occupying(): array
    {
        return [self::PendingPayment, self::Confirmed];
    }

    public function occupiesASeat(): bool
    {
        return in_array($this, self::occupying(), strict: true);
    }
}
