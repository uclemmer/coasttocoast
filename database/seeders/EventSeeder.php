<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The fair calendar: five past fairs and the next one.
 *
 * These exist so there is somewhere to import history *into*. The historical
 * roster import (`fair:import-roster`, doc 11) resolves each CSV row by
 * `event_slug` and skips any row naming a fair that is not in the database, so
 * seeding the back catalogue is a precondition of that import rather than
 * decoration.
 *
 * Five years, not two, because every cross-year audience is a set difference
 * over the fair history (doc 07 §2): `LastEvent` minus `AnyPreviousEvent` is
 * how a lapsed list is built, and the depth of that history is the depth of the
 * win-back campaign. Two years made those queries *testable*; five make them
 * useful.
 *
 * ## What is confirmed and what is reconstructed
 *
 * Only the 2026 fair is confirmed — Tuesday 21 April 2026, $215, from the live
 * site (doc 00). The venue is confirmed for all of them. **The 2022–2025 dates
 * and prices are reconstructions**: the fourth-ish Tuesday of April at the same
 * hours, with the fee stepping down into the past. They are plausible, not
 * researched.
 *
 * That is deliberately tolerable rather than sloppy. Nothing downstream reads a
 * past fair's `price_cents` — a registration snapshots what it actually paid
 * into `registrations.price_cents` at the time, and the import CSV carries a
 * per-row `price_cents` for exactly this reason. A past fair's list price is a
 * record, not an input. All six rows are editable in the admin panel, so
 * correcting one is an edit rather than a deploy.
 *
 * ## Two fairs in one year
 *
 * Supported, and nothing needs changing to allow it. Every "which fair" query
 * in the app orders by `starts_at` rather than grouping by year — `active()`,
 * the `previousPublished()` scope behind the Last Year roster, and every
 * cross-year audience. A second fair in a year is one more row.
 *
 * The only thing a year buys is the naming convention, which is why the slug
 * and the name are written out per fair below instead of being derived from a
 * year. While the fair is annual, `college-fair-2026` reads best; the day a
 * year holds two, they become `college-fair-spring-2026` and
 * `college-fair-fall-2026` and this array grows by a line. Existing slugs must
 * not be renamed to match — they are public URLs and the import CSV's join key.
 *
 * Idempotent by slug: re-running never edits a fair the coordinator has since
 * corrected.
 */
class EventSeeder extends Seeder
{
    protected const VENUE_NAME = 'Chattanooga Convention & Trade Center';

    protected const VENUE_ADDRESS = "1150 Carter Street\nChattanooga, TN 37402";

    public function run(): void
    {
        foreach ($this->fairs() as $fair) {
            $this->fair(...$fair);
        }
    }

    /**
     * The calendar, oldest first.
     *
     * @return list<array{slug: string, name: string, date: string, priceCents: int, published?: bool, registrationOpensAt?: string, registrationClosesAt?: string}>
     */
    protected function fairs(): array
    {
        return [
            // --- The back catalogue. Published, past, and waiting for their
            // rosters to be imported. Dates and prices reconstructed; see the
            // class docblock.
            ['slug' => 'college-fair-2022', 'name' => 'College Fair 2022', 'date' => '2022-04-26 18:30', 'priceCents' => 17500],
            ['slug' => 'college-fair-2023', 'name' => 'College Fair 2023', 'date' => '2023-04-25 18:30', 'priceCents' => 17500],
            ['slug' => 'college-fair-2024', 'name' => 'College Fair 2024', 'date' => '2024-04-23 18:30', 'priceCents' => 19500],
            ['slug' => 'college-fair-2025', 'name' => 'College Fair 2025', 'date' => '2025-04-22 18:30', 'priceCents' => 19500],

            // 2026 — confirmed from the live site: Tuesday, April 21, 2026, $215.
            ['slug' => 'college-fair-2026', 'name' => 'College Fair 2026', 'date' => '2026-04-21 18:30', 'priceCents' => 21500],

            // 2027 — TODO-OWNER. The date and price are NOT confirmed (doc 01
            // open questions; standing answer A4 in doc 10 authorised a
            // placeholder). Left UNPUBLISHED on purpose: an unpublished event
            // cannot take money, so a forgotten placeholder cannot quietly
            // charge an organization the wrong fee. Publishing it is the
            // coordinator's deliberate act once the real figures are known.
            [
                'slug' => 'college-fair-2027',
                'name' => 'College Fair 2027 (date and price not yet confirmed — TODO-OWNER)',
                'date' => '2027-04-20 18:30',
                'priceCents' => 21500,
                'published' => false,
                'registrationOpensAt' => '2026-12-01 09:00',
                'registrationClosesAt' => '2027-04-06 17:00',
            ],
        ];
    }

    protected function fair(
        string $slug,
        string $name,
        string $date,
        int $priceCents,
        bool $published = true,
        ?string $registrationOpensAt = null,
        ?string $registrationClosesAt = null,
    ): Event {
        $starts = Carbon::parse($date);

        return Event::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->setTime(20, 0),
                // Counselor reception, 5:00-6:30 PM (doc 00).
                'reception_starts_at' => $starts->copy()->setTime(17, 0),
                'venue_name' => self::VENUE_NAME,
                'venue_address' => self::VENUE_ADDRESS,
                'price_cents' => $priceCents,
                'capacity' => null,
                'registration_opens_at' => $registrationOpensAt
                    ? Carbon::parse($registrationOpensAt)
                    : $starts->copy()->subMonths(5),
                'registration_closes_at' => $registrationClosesAt
                    ? Carbon::parse($registrationClosesAt)
                    : $starts->copy()->subWeeks(2),
                'is_published' => $published,
            ],
        );
    }
}
