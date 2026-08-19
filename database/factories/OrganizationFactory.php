<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * `normalized_name` is deliberately absent: the model derives it on save,
     * and a factory that set it by hand would let a test pass with a value the
     * application could never produce.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' '.fake()->randomElement(['University', 'College', 'State University']);

        return [
            'name' => $name,
            'website' => 'https://'.fake()->unique()->domainName(),
            'logo_path' => null,
            'admissions_office' => 'Office of Admissions',
            'admissions_email' => fake()->unique()->companyEmail(),
            'admissions_phone' => '+1'.fake()->numerify('##########'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'created_by' => null,
        ];
    }

    /**
     * A school with no generic contact — the case where the campaign fallback
     * has nothing to fall back to and the recipient is dropped with a log
     * (doc 07 §2 rule 1).
     */
    public function withoutAdmissionsEmail(): static
    {
        return $this->state(fn (array $attributes) => ['admissions_email' => null]);
    }

    public function withLogo(): static
    {
        return $this->state(fn (array $attributes) => [
            'logo_path' => 'organization-logos/'.fake()->uuid().'.png',
        ]);
    }

    /**
     * Force an exact name, for duplicate-detection tests that need two schools
     * to normalize to the same string.
     */
    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => ['name' => $name]);
    }
}
