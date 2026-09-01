<?php

namespace App\Support;

/**
 * The two conversions between what a person types and what the database
 * stores.
 *
 * Money is integer cents everywhere in this app (doc 02 convention 3), but a
 * coordinator types dollars and a receipt shows dollars. Both directions live
 * here so no form, PDF or email reinvents them — and in particular so nobody
 * writes `$dollars * 100` and loses a cent to floating point on every
 * hundredth registration.
 */
final class Money
{
    /**
     * Dollars (as typed: "215", "215.5", "215.50") to integer cents.
     *
     * `round()` before casting, not `(int)` alone: 215.10 * 100 is
     * 21509.999999999996 in IEEE 754, and casting that truncates to 21509 —
     * an organization charged a cent less than it agreed to, silently, forever.
     */
    public static function toCents(float|int|string|null $dollars): int
    {
        if ($dollars === null || $dollars === '') {
            return 0;
        }

        return (int) round(((float) $dollars) * 100);
    }

    /**
     * Integer cents to a dollars value suitable for a numeric form field.
     */
    public static function toDollars(?int $cents): float
    {
        return ($cents ?? 0) / 100;
    }

    /**
     * Integer cents as display text: `$215.00`.
     */
    public static function format(?int $cents): string
    {
        return '$'.number_format(($cents ?? 0) / 100, 2);
    }
}
