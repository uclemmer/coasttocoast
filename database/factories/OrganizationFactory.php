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
     * An organization with no generic contact — the case where the campaign fallback
     * has nothing to fall back to and the recipient is dropped with a log
     * (doc 07 §2 rule 1).
     */
    public function withoutAdmissionsEmail(): static
    {
        return $this->state(fn (array $attributes) => ['admissions_email' => null]);
    }

    /**
     * An organization with a name and nothing else about the institution.
     *
     * For fixtures that name a REAL college. The invented website, inbox,
     * phone and address are fine on an invented name and actively wrong on a
     * real one — and because `OrganizationSeeder` and `AdmissionsOfficeSeeder`
     * both only fill columns that are EMPTY, an invented value does not merely
     * sit there looking odd: it blocks the researched one from ever landing.
     * That is how `https://sawayn.com` ended up on Rhodes College.
     *
     * Note this does not clear `name`, which is the point, or `logo_path`,
     * which the factory leaves null anyway.
     */
    public function withoutInstitutionalProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'website' => null,
            'admissions_office' => null,
            'admissions_email' => null,
            'admissions_phone' => null,
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
        ]);
    }

    public function withLogo(): static
    {
        return $this->state(fn (array $attributes) => [
            'logo_path' => 'organization-logos/'.fake()->uuid().'.png',
        ]);
    }

    /**
     * Force an exact name, for duplicate-detection tests that need two organizations
     * to normalize to the same string.
     */
    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => ['name' => $name]);
    }
}
