<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * An unpublished future fair with no registration window — the most inert
     * default there is, so that a test which cares about openness has to say
     * so. Doing it the other way round hides window bugs behind a factory.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = Carbon::now()->addMonths(6)->setTime(18, 30);

        return [
            'name' => 'College Fair '.$starts->year,
            'slug' => 'college-fair-'.$starts->year.'-'.fake()->unique()->numerify('####'),
            'starts_at' => $starts,
            'ends_at' => (clone $starts)->setTime(20, 0),
            'reception_starts_at' => (clone $starts)->setTime(17, 30),
            'venue_name' => fake()->company().' Convention Center',
            'venue_address' => fake()->streetAddress()."\n".fake()->city().', '.fake()->stateAbbr().' '.fake()->postcode(),
            'price_cents' => 21500,
            'capacity' => null,
            'registration_opens_at' => null,
            'registration_closes_at' => null,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => true]);
    }

    /**
     * Published, and accepting registrations right now.
     */
    public function registrationOpen(): static
    {
        return $this->published()->state(fn (array $attributes) => [
            'registration_opens_at' => Carbon::now()->subWeek(),
            'registration_closes_at' => Carbon::now()->addWeek(),
        ]);
    }

    /**
     * Published, but the window has passed — the state that shows the interest
     * form on the public event page (card 5.4).
     */
    public function registrationClosed(): static
    {
        return $this->published()->state(fn (array $attributes) => [
            'registration_opens_at' => Carbon::now()->subMonths(2),
            'registration_closes_at' => Carbon::now()->subWeek(),
        ]);
    }

    /**
     * Published, with a window that has not started — the date-notice state,
     * which is NOT the same as closed.
     */
    public function registrationNotYetOpen(): static
    {
        return $this->published()->state(fn (array $attributes) => [
            'registration_opens_at' => Carbon::now()->addWeek(),
            'registration_closes_at' => Carbon::now()->addMonths(3),
        ]);
    }

    /**
     * A fair that has already happened. Cross-year audiences and the Last Year
     * roster both need these, and both only count published ones.
     */
    public function past(int $yearsAgo = 1): static
    {
        $starts = Carbon::now()->subYears($yearsAgo)->setTime(18, 30);

        return $this->published()->state(fn (array $attributes) => [
            'name' => 'College Fair '.$starts->year,
            'slug' => 'college-fair-'.$starts->year.'-'.fake()->unique()->numerify('####'),
            'starts_at' => $starts,
            'ends_at' => (clone $starts)->setTime(20, 0),
            'reception_starts_at' => (clone $starts)->setTime(17, 30),
            'registration_opens_at' => (clone $starts)->subMonths(4),
            'registration_closes_at' => (clone $starts)->subWeeks(2),
        ]);
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(fn (array $attributes) => ['capacity' => $capacity]);
    }

    public function priced(int $cents): static
    {
        return $this->state(fn (array $attributes) => ['price_cents' => $cents]);
    }
}
