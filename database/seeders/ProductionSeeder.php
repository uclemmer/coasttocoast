<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * What a real host needs to come up: roles, the coordinator's account, the
 * editable page copy, the sponsors, the FAQ and the fair calendar.
 *
 * Deliberately excludes `FairFixtureSeeder` — no invented organizations, reps,
 * registrations, grants or payments ever reach production. Historical rosters
 * are a real import (card 6.6), not a fixture.
 *
 * Every seeder it calls is idempotent, so this is safe to re-run on deploy:
 *
 *     php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --force
 *
 * Note that `EventSeeder` leaves the 2027 fair UNPUBLISHED, because its date
 * and price are placeholders. Publishing it is the coordinator's deliberate
 * act once the real figures are known — see the TODO-OWNER note in that class.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CoordinatorSeeder::class,
            ContentBlockSeeder::class,
            SponsorSeeder::class,
            FaqSeeder::class,
            EventSeeder::class,
        ]);
    }
}
