<?php

namespace Database\Factories;

use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsor>
 */
class SponsorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'website' => 'https://'.fake()->unique()->domainName(),
            'logo_path' => null,
            'sort_order' => 0,
        ];
    }

    public function withLogo(): static
    {
        return $this->state(fn (array $attributes) => [
            'logo_path' => 'sponsor-logos/'.fake()->uuid().'.png',
        ]);
    }

    public function ordered(int $position): static
    {
        return $this->state(fn (array $attributes) => ['sort_order' => $position]);
    }
}
