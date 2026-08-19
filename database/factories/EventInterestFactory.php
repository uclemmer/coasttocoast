<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventInterest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<EventInterest>
 */
class EventInterestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'email' => fake()->unique()->safeEmail(),
            'organization_name' => fake()->company().' University',
            'notified_at' => null,
        ];
    }

    public function notified(): static
    {
        return $this->state(fn (array $attributes) => ['notified_at' => Carbon::now()]);
    }

    /**
     * Someone who left an address and nothing else — the minimum the form asks
     * for (R2.7).
     */
    public function withoutOrganizationName(): static
    {
        return $this->state(fn (array $attributes) => ['organization_name' => null]);
    }
}
