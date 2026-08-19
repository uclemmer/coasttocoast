<?php

namespace Database\Factories;

use App\Models\StripeWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<StripeWebhookEvent>
 */
class StripeWebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stripe_event_id' => 'evt_test_'.fake()->unique()->lexify('??????????????????'),
            'type' => 'checkout.session.completed',
            'payload' => ['id' => 'evt_test', 'type' => 'checkout.session.completed'],
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => ['processed_at' => Carbon::now()]);
    }
}
