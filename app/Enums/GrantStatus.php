<?php

namespace App\Enums;

/**
 * An organization's application for free or discounted registration (D10).
 *
 * Grants are never hard-deleted (doc 03, data lifecycle): `Denied`, `Revoked`
 * and `Withdrawn` are all terminal records rather than absences. Only
 * `Approved` changes what an organization pays.
 */
enum GrantStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Revoked = 'revoked';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('Under review'),
            self::Approved => __('Approved'),
            self::Denied => __('Denied'),
            self::Revoked => __('Revoked'),
            self::Withdrawn => __('Withdrawn'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Denied => 'danger',
            self::Revoked => 'danger',
            self::Withdrawn => 'gray',
        };
    }

    /**
     * Statuses that block a second application for the same organization and
     * event (doc 05 card 2.6). Only a withdrawn application frees the slot —
     * a denial is final for that event.
     *
     * @return array<int, self>
     */
    public static function blockingReapplication(): array
    {
        return [self::Pending, self::Approved, self::Denied, self::Revoked];
    }

    /**
     * Whether this grant reduces the price. `priceFor()` consults exactly this
     * (doc 03) — pending, denied, revoked and withdrawn grants are invisible
     * to pricing.
     */
    public function discountsPrice(): bool
    {
        return $this === self::Approved;
    }
}
