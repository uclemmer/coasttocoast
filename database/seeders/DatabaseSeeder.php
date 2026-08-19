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
 * schools onto them.
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
    }
}
