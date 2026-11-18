<?php

namespace Database\Seeders;

use App\Models\Sponsor;
use Illuminate\Database\Seeder;

/**
 * The four sponsoring preparatory schools (doc 00).
 *
 * Their college counseling staff are listed on the public Sponsors page. The
 * names and titles are the live site's Sponsors page as read on 2026-11-17
 * (docs/29 §9), in its order. Until then only Meg Conner was seeded.
 *
 * Idempotent by name, sponsor and person alike. Re-running it adds anyone
 * missing and never rewrites a title or order a coordinator has edited, so
 * an existing database keeps Meg Conner's earlier seeded title.
 */
class SponsorSeeder extends Seeder
{
    public function run(): void
    {
        $sponsors = [
            ['name' => 'Baylor School', 'website' => 'https://www.baylorschool.org', 'staff' => [
                ['name' => 'Ann Katherine Taylor', 'title' => 'Director of College Counseling'],
                ['name' => 'Liz Young', 'title' => 'Senior Associate Director of College Counseling'],
                ['name' => 'Meg Conner', 'title' => 'Associate Director of College Counseling'],
                ['name' => 'Jake Kennedy', 'title' => 'Associate Director of College Counseling'],
                ['name' => "Kelly O'Mara", 'title' => 'Associate Director of College Counseling'],
            ]],
            ['name' => 'Girls Preparatory School', 'website' => 'https://www.gps.edu', 'staff' => [
                ['name' => 'Andrew Reich', 'title' => 'Director of College Counseling'],
                ['name' => 'Anders Swanson', 'title' => 'Associate Director of College Counseling'],
            ]],
            ['name' => 'McCallie School', 'website' => 'https://www.mccallie.org', 'staff' => [
                ['name' => 'Abbie Roberts', 'title' => 'Director of College Counseling'],
                ['name' => 'Megan Cooper', 'title' => 'Associate Director of College Counseling'],
                ['name' => 'Erik Johnson', 'title' => 'Associate Director of College Counseling'],
                ['name' => 'Alex McCallie', 'title' => 'Associate Director of College Counseling'],
            ]],
            ['name' => 'St. Andrews-Sewanee School', 'website' => 'https://www.sasweb.org', 'staff' => [
                ['name' => 'Bob MacLellan', 'title' => 'Director of College Counseling'],
            ]],
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
