<?php

namespace Database\Factories;

use App\Models\Sponsor;
use App\Models\SponsorStaff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsorStaff>
 */
class SponsorStaffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sponsor_id' => Sponsor::factory(),
            'name' => fake()->name(),
            'title' => fake()->jobTitle(),
            'sort_order' => 0,
        ];
    }
}
