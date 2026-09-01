<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The development seed: everything `ProductionSeeder` does, plus the fixture
 * set that makes the app worth looking at (doc 03).
 *
 * Order matters. Roles before the coordinator, because the coordinator is
 * assigned one. Events before the fixtures, because the fixtures register
 * organizations onto them.
 *
 * The two participant-export seeders come LAST, and both halves of that are
 * load-bearing. `FairFixtureSeeder` does nothing at all if any organization
 * already exists, so putting the real history first would silently cost the
 * fixtures — the membership states, the grants, the duplicate pair, the
 * awkward registrations. And `RegistrationSeeder` needs `OrganizationSeeder`
 * to have run, because it will not invent an organization it cannot find.
 *
 * `ProductionSeeder` deliberately does NOT call them. The history is real, but
 * that seeder's contract is that it invents nothing and runs on every deploy;
 * loading a roster is a deliberate one-off, either these two by name or
 * `fair:import-roster`. See the note in `OrganizationSeeder`.
 *
 * `WithoutModelEvents` is deliberately NOT used: `Organization` derives
 * `normalized_name` in a saving hook and `Event` fills a blank slug the same
 * way, so a seed with model events muted would write rows the application
 * itself could never produce — and the duplicate-detection fixtures in
 * particular would silently seed as non-duplicates.
 */
class DatabaseSeeder extends Seeder
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
            FairFixtureSeeder::class,
            OrganizationSeeder::class,
            RegistrationSeeder::class,
        ]);
    }
}
