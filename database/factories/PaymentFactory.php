<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'method' => PaymentMethod::Stripe,
            'status' => PaymentStatus::Succeeded,
            'amount_cents' => 21500,
            'currency' => 'usd',
            'stripe_checkout_session_id' => 'cs_test_'.fake()->unique()->lexify('??????????????????'),
            'stripe_payment_intent_id' => 'pi_test_'.fake()->unique()->lexify('??????????????????'),
            'check_number' => null,
            'check_received_on' => null,
            'recorded_by' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Pending,
            'stripe_payment_intent_id' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Failed]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Refunded]);
    }

    /**
     * A check the coordinator recorded. No Stripe identifiers at all — those
     * columns belong to the other path and leaving them populated would make a
     * check look like a card payment to anything querying by session id.
     */
    public function check(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::Check,
            'status' => PaymentStatus::Succeeded,
            'stripe_checkout_session_id' => null,
            'stripe_payment_intent_id' => null,
            'check_number' => (string) fake()->numberBetween(1000, 9999),
            'check_received_on' => Carbon::now()->toDateString(),
            'recorded_by' => User::factory()->coordinator(),
        ]);
    }
}
