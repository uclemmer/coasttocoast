<?php

namespace Database\Factories;

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Models\Event;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Grant>
 */
class GrantFactory extends Factory
{
    /**
     * A pending application: no benefit, no decision. Everything that changes
     * a price has to be asked for explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'requested_by' => User::factory(),
            'justification' => fake()->paragraph(),
            'status' => GrantStatus::Pending,
            'benefit_type' => null,
            'custom_price_cents' => null,
            'percent_off' => null,
            'decided_by' => null,
            'decided_at' => null,
            'denial_reason' => null,
        ];
    }

    /**
     * The default state, named so datasets can ask for it alongside the others.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GrantStatus::Pending,
            'benefit_type' => null,
            'decided_by' => null,
            'decided_at' => null,
        ]);
    }

    /**
     * Approved for a free registration — the path that confirms with no
     * payment at all (test inventory item 1a).
     */
    public function free(): static
    {
        return $this->approvedWith(GrantBenefit::Free);
    }

    public function customPrice(int $cents): static
    {
        return $this->approvedWith(GrantBenefit::CustomPrice, ['custom_price_cents' => $cents]);
    }

    public function percentOff(int $percent): static
    {
        return $this->approvedWith(GrantBenefit::PercentOff, ['percent_off' => $percent]);
    }

    public function denied(string $reason = 'Funds for this fair are already committed.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GrantStatus::Denied,
            'decided_by' => User::factory()->coordinator(),
            'decided_at' => Carbon::now(),
            'denial_reason' => $reason,
        ]);
    }

    /**
     * Revoked after approval. Keeps the benefit columns populated on purpose:
     * a revoked grant must still show what it *was* worth, and pricing must
     * ignore it anyway because it reads status, not benefit.
     */
    public function revoked(): static
    {
        return $this->free()->state(fn (array $attributes) => [
            'status' => GrantStatus::Revoked,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => ['status' => GrantStatus::Withdrawn]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function approvedWith(GrantBenefit $benefit, array $extra = []): static
    {
        return $this->state(fn (array $attributes) => array_merge([
            'status' => GrantStatus::Approved,
            'benefit_type' => $benefit,
            'decided_by' => User::factory()->coordinator(),
            'decided_at' => Carbon::now(),
        ], $extra));
    }
}
