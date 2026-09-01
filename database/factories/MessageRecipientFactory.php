<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageRecipient>
 */
class MessageRecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'registration_id' => null,
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'organization_name' => fake()->company().' University',
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'email_status' => DeliveryStatus::Pending,
            'sms_status' => DeliveryStatus::Skipped,
            'email_log_id' => null,
            'error' => null,
        ];
    }

    /**
     * The admissions_email fallback: an organization with nobody behind it, so there
     * is an organization but no user (doc 07 §2 rule 1).
     */
    public function generic(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'name' => null,
        ]);
    }

    /**
     * An interest-list recipient: an address and nothing else.
     */
    public function interestOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'organization_id' => null,
            'organization_name' => null,
            'name' => null,
        ]);
    }

    public function withPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => '+1'.fake()->numerify('##########'),
        ]);
    }
}
