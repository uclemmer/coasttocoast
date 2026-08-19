<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The fairs themselves: two past years and the next one.
 *
 * Two past events, not one, because the cross-year audiences need them — the
 * difference between `LastEvent` and `AnyPreviousEvent` is invisible with a
 * single year of history (doc 07 §2), and so is the lapsed-set subtraction.
 *
 * The venue and the 2026 date and price come from the live site (doc 00). The
 * 2025 figures are reasonable reconstructions, and 2027 is a placeholder — see
 * the TODO-OWNER note below.
 *
 * Idempotent by slug.
 */
class EventSeeder extends Seeder
{
    protected const VENUE_NAME = 'Chattanooga Convention & Trade Center';

    protected const VENUE_ADDRESS = "1150 Carter Street\nChattanooga, TN 37402";

    public function run(): void
    {
        // 2025 — the older past fair. Date and price reconstructed; nothing
        // downstream depends on them being exact, only on their being past,
        // published and distinct from 2026.
        $this->fair(
            year: 2025,
            date: Carbon::parse('2025-04-22 18:30'),
            priceCents: 19500,
        );

        // 2026 — confirmed from the live site: Tuesday, April 21, 2026, $215.
        $this->fair(
            year: 2026,
            date: Carbon::parse('2026-04-21 18:30'),
            priceCents: 21500,
        );

        // 2027 — TODO-OWNER. The date and price are NOT confirmed (doc 01 open
        // questions; standing answer A4 in doc 10 authorised a placeholder).
        // Left UNPUBLISHED on purpose: an unpublished event cannot take money,
        // so a forgotten placeholder cannot quietly charge a school the wrong
        // fee. Publishing it is the coordinator's deliberate act once the real
        // date and price are known.
        $this->fair(
            year: 2027,
            date: Carbon::parse('2027-04-20 18:30'),
            priceCents: 21500,
            published: false,
            registrationOpensAt: Carbon::parse('2026-12-01 09:00'),
            registrationClosesAt: Carbon::parse('2027-04-06 17:00'),
            name: 'College Fair 2027 (date and price not yet confirmed — TODO-OWNER)',
        );
    }

    protected function fair(
        int $year,
        Carbon $date,
        int $priceCents,
        bool $published = true,
        ?Carbon $registrationOpensAt = null,
        ?Carbon $registrationClosesAt = null,
        ?string $name = null,
    ): Event {
        return Event::query()->firstOrCreate(
            ['slug' => 'college-fair-'.$year],
            [
                'name' => $name ?? 'College Fair '.$year,
                'starts_at' => $date,
                'ends_at' => (clone $date)->setTime(20, 0),
                // Counselor reception, 5:00-6:30 PM (doc 00).
                'reception_starts_at' => (clone $date)->setTime(17, 0),
                'venue_name' => self::VENUE_NAME,
                'venue_address' => self::VENUE_ADDRESS,
                'price_cents' => $priceCents,
                'capacity' => null,
                'registration_opens_at' => $registrationOpensAt ?? (clone $date)->subMonths(5),
                'registration_closes_at' => $registrationClosesAt ?? (clone $date)->subWeeks(2),
                'is_published' => $published,
            ],
        );
    }
}
