<?php

namespace App\Enums;

/**
 * How an organization paid.
 *
 * Deliberately has no `free` case: a registration priced at 0 by an approved
 * grant has a NULL payment method, because no payment ever happens (doc 03,
 * registrations table). Adding `Free` here would invite a payment row for
 * money that never moved.
 */
enum PaymentMethod: string
{
    case Stripe = 'stripe';
    case Check = 'check';

    public function getLabel(): string
    {
        return match ($this) {
            self::Stripe => __('Credit card'),
            self::Check => __('Check by mail'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stripe => 'info',
            self::Check => 'gray',
        };
    }
}
