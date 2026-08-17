<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Development seed. The realistic fixture set (past events with rosters,
 * sponsors, FAQ, content blocks) arrives with card 1.3; for now this is roles
 * and permissions only.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);
    }
}
