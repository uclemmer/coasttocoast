<?php

use App\Http\Requests\StoreEventInterestRequest;
use App\Models\Event as Fair;
use App\Models\EventInterest;

beforeEach(function () {
    $this->fair = Fair::factory()->registrationClosed()->create();
});

describe('capturing interest', function () {
    it('records an email and an optional school name', function () {
        // The gap in the current site: registration is shut most of the year
        // and the page is a dead end (doc 00).
        $this->post(route('events.interest', $this->fair), [
            'email' => 'dana@kenyon.example',
            'organization_name' => 'Kenyon College',
        ])->assertRedirect()->assertSessionHas('status');

        expect(EventInterest::query()->where('event_id', $this->fair->id)->first())
            ->email->toBe('dana@kenyon.example')
            ->organization_name->toBe('Kenyon College')
            ->notified_at->toBeNull();
    });

    it('accepts an email on its own', function () {
        // Somebody who cannot spell their institution's official name should
        // still get told.
        $this->post(route('events.interest', $this->fair), ['email' => 'dana@kenyon.example'])
            ->assertSessionHasNoErrors();

        expect(EventInterest::query()->count())->toBe(1);
    });

    it('does not sign the same person up twice', function () {
        $this->post(route('events.interest', $this->fair), ['email' => 'dana@kenyon.example']);
        $this->post(route('events.interest', $this->fair), [
            'email' => 'dana@kenyon.example',
            'organization_name' => 'Kenyon College',
        ]);

        expect(EventInterest::query()->count())->toBe(1)
            // The second submission still improves what we know.
            ->and(EventInterest::query()->first()->organization_name)->toBe('Kenyon College');
    });

    it('treats addresses case-insensitively', function () {
        // Otherwise the same person is told they are on the list AND mailed
        // twice.
        $this->post(route('events.interest', $this->fair), ['email' => 'Dana@Kenyon.example']);
        $this->post(route('events.interest', $this->fair), ['email' => 'dana@kenyon.example']);

        expect(EventInterest::query()->count())->toBe(1);
    });

    it('keeps the lists for two fairs apart', function () {
        $other = Fair::factory()->registrationClosed()->create();

        $this->post(route('events.interest', $this->fair), ['email' => 'dana@kenyon.example']);
        $this->post(route('events.interest', $other), ['email' => 'dana@kenyon.example']);

        expect(EventInterest::query()->count())->toBe(2);
    });

    it('requires something that looks like an email', function () {
        $this->post(route('events.interest', $this->fair), ['email' => 'not-an-address'])
            ->assertSessionHasErrors('email');

        expect(EventInterest::query()->count())->toBe(0);
    });
});

describe('abuse controls', function () {
    it('drops a submission that filled in the honeypot', function () {
        $this->post(route('events.interest', $this->fair), [
            'email' => 'bot@example.com',
            StoreEventInterestRequest::HONEYPOT => 'https://spam.example',
        ])->assertSessionHasErrors(StoreEventInterestRequest::HONEYPOT);

        expect(EventInterest::query()->count())->toBe(0);
    });

    it('rate-limits by IP, because this is an unauthenticated write', function () {
        foreach (range(1, 5) as $i) {
            $this->post(route('events.interest', $this->fair), ['email' => "person{$i}@example.edu"])
                ->assertSessionHasNoErrors();
        }

        $this->post(route('events.interest', $this->fair), ['email' => 'sixth@example.edu'])
            ->assertStatus(429);

        expect(EventInterest::query()->count())->toBe(5);
    });
});
