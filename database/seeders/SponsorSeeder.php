<?php

namespace Database\Seeders;

use App\Models\Sponsor;
use Illuminate\Database\Seeder;

/**
 * The four sponsoring preparatory schools (doc 00).
 *
 * Their college counseling staff are listed on the public Sponsors page. Real
 * names beyond the coordinator's are not in doc 00, so only Meg Conner is
 * seeded; the rest is a coordinator task in the admin panel.
 *
 * Idempotent by name.
 */
class SponsorSeeder extends Seeder
{
    public function run(): void
    {
        $sponsors = [
            ['name' => 'Baylor School', 'website' => 'https://www.baylorschool.org', 'staff' => [
                ['name' => 'Meg Conner', 'title' => 'Fair Coordinator, College Counseling'],
            ]],
            ['name' => 'Girls Preparatory School', 'website' => 'https://www.gps.edu', 'staff' => []],
            ['name' => 'McCallie School', 'website' => 'https://www.mccallie.org', 'staff' => []],
            ['name' => 'St. Andrews-Sewanee School', 'website' => 'https://www.sasweb.org', 'staff' => []],
        ];

        foreach ($sponsors as $position => $sponsor) {
            $record = Sponsor::query()->firstOrCreate(
                ['name' => $sponsor['name']],
                ['website' => $sponsor['website'], 'sort_order' => $position],
            );

            foreach ($sponsor['staff'] as $order => $person) {
                $record->staff()->firstOrCreate(
                    ['name' => $person['name']],
                    ['title' => $person['title'], 'sort_order' => $order],
                );
            }
        }
    }
}
