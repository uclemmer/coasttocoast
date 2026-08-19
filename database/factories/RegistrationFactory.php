<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * A confirmed, card-paid registration at list price — the shape most tests
     * want as a backdrop.
     *
     * `price_cents` is a literal here rather than a call to `Event::priceFor()`.
     * That is on purpose: the snapshot is the thing under test in the pricing
     * suite, and a factory that recomputed it would make those tests tautologies.
     * Tests that care about grant-aware pricing go through `RegistrationService`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'status' => RegistrationStatus::Confirmed,
            'payment_method' => PaymentMethod::Stripe,
            'grant_id' => null,
            'price_cents' => 21500,
            'rep_name' => fake()->name(),
            'rep_email' => fake()->unique()->safeEmail(),
            'rep_phone' => null,
            'show_on_roster' => true,
            'notes' => null,
            'confirmed_at' => Carbon::now(),
            'cancelled_at' => null,
        ];
    }

    public function pendingStripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::PendingPayment,
            'payment_method' => PaymentMethod::Stripe,
            'confirmed_at' => null,
        ]);
    }

    public function pendingCheck(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::PendingPayment,
            'payment_method' => PaymentMethod::Check,
            'confirmed_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Cancelled,
            'confirmed_at' => null,
            'cancelled_at' => Carbon::now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Refunded,
        ]);
    }

    /**
     * A registration a grant made free. Note the null payment method: nothing
     * was ever charged, so there is no method to record (doc 03).
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_cents' => 0,
            'payment_method' => null,
            'status' => RegistrationStatus::Confirmed,
            'confirmed_at' => Carbon::now(),
        ]);
    }

    /**
     * A coordinator's manual entry, or an imported historical row: no account
     * behind it, only the contact snapshot.
     */
    public function manualEntry(): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => null]);
    }

    public function hiddenFromRoster(): static
    {
        return $this->state(fn (array $attributes) => ['show_on_roster' => false]);
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn (array $attributes) => ['event_id' => $event->getKey()]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => ['organization_id' => $organization->getKey()]);
    }
}
