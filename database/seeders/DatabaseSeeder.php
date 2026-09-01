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
 * They are also the only two that can be absent. The export they read is real
 * contact data and is deliberately not in the repository, so a developer who
 * has not been given it gets the fixtures and a warning naming the file rather
 * than a seed that dies — while running either seeder by name without it still
 * throws, because there the roster is the thing that was asked for.
 *
 * `AdmissionsOfficeSeeder` follows them and runs either way: its own data file
 * is committed, and it only ever updates organizations that already exist.
 *
 * `ProductionSeeder` deliberately does NOT call any of the three. The data is
 * real, but that seeder's contract is that it invents nothing and runs on every
 * deploy; loading a roster is a deliberate one-off, either these by name or
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
        ]);

        if (ParticipantExportSeeder::available()) {
            $this->call([
                OrganizationSeeder::class,
                RegistrationSeeder::class,
            ]);
        } else {
            $this->command?->warn('No participant export at '.ParticipantExportSeeder::path().' — seeded the fixtures only, with no real roster history.');
        }

        // Unconditional: its own data file is committed, and it only ever
        // updates organizations that already exist. Without the export that is
        // just the fixtures, several of which are real institutions by name.
        $this->call([
            AdmissionsOfficeSeeder::class,
        ]);
    }
}
