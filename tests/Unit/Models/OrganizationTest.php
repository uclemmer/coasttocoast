<?php

use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('name normalization', function () {
    // The soft duplicate check (R2.7) and the historical import (card 6.6) both
    // hang off this, so its edges are worth pinning precisely.
    it('normalizes case, punctuation, whitespace and a leading "the"', function (string $input, string $expected) {
        expect(Organization::normalizeName($input))->toBe($expected);
    })->with([
        'plain' => ['Ohio State University', 'ohio state university'],
        'leading the' => ['The Ohio State University', 'ohio state university'],
        'trailing punctuation' => ['Ohio State University.', 'ohio state university'],
        'inner punctuation' => ['St. Mary\'s College', 'st mary s college'],
        'collapsed whitespace' => ["  Ohio   State\tUniversity  ", 'ohio state university'],
        'ampersand' => ['Texas A&M University', 'texas a m university'],
        'mixed case' => ['bOsToN cOlLeGe', 'boston college'],
    ]);

    it('keeps organizations that differ only by University or College apart', function () {
        // Stripping those words would merge Boston University into Boston
        // College, which is worse than a missed duplicate warning.
        expect(Organization::normalizeName('Boston University'))
            ->not->toBe(Organization::normalizeName('Boston College'));
    });

    it('derives normalized_name on save without being asked', function () {
        $organization = Organization::factory()->named('The University of Example')->create();

        expect($organization->normalized_name)->toBe('university of example');
    });

    it('re-derives normalized_name when the name changes', function () {
        $organization = Organization::factory()->named('Example College')->create();

        $organization->update(['name' => 'Example State University']);

        expect($organization->fresh()->normalized_name)->toBe('example state university');
    });
});

describe('duplicate detection', function () {
    it('finds organizations whose names normalize the same way', function () {
        $existing = Organization::factory()->named('The Ohio State University')->create();
        $candidate = Organization::factory()->named('ohio state university.')->create();

        expect($candidate->possibleDuplicates()->pluck('id')->all())->toBe([$existing->id]);
    });

    it('does not report a saved organization as its own duplicate', function () {
        $organization = Organization::factory()->create();

        expect($organization->possibleDuplicates()->count())->toBe(0);
    });

    it('finds duplicates for an organization that has not been saved yet', function () {
        // This is the signup case: the warning has to appear before the record
        // exists, or it appears too late to be useful.
        $existing = Organization::factory()->named('Kenyon College')->create();

        $candidate = new Organization(['name' => 'kenyon college']);

        expect($candidate->possibleDuplicates()->pluck('id')->all())->toBe([$existing->id]);
    });

    it('matches by name through the scope', function () {
        $existing = Organization::factory()->named('The Kenyon College')->create();

        expect(Organization::query()->matchingName('kenyon college')->pluck('id')->all())
            ->toBe([$existing->id]);
    });
});

describe('relationships', function () {
    it('resolves users, registrations, grants and the creator', function () {
        $creator = User::factory()->create();
        $organization = Organization::factory()->create(['created_by' => $creator->id]);
        User::factory()->count(2)->rep($organization)->create();
        Registration::factory()->forOrganization($organization)->create();
        Grant::factory()->for($organization)->create();

        expect($organization->users()->count())->toBe(2)
            ->and($organization->registrations()->count())->toBe(1)
            ->and($organization->grants()->count())->toBe(1)
            ->and($organization->creator->is($creator))->toBeTrue();
    });

    it('counts only active reps as the people who speak for it', function () {
        $organization = Organization::factory()->create();
        $active = User::factory()->rep($organization)->create();
        User::factory()->pendingRep($organization)->create();
        User::factory()->retiredRep($organization)->create();

        expect($organization->activeReps()->pluck('id')->all())->toBe([$active->id])
            ->and($organization->users()->count())->toBe(3);
    });
});

describe('formatted address', function () {
    it('renders the address across lines', function () {
        $organization = Organization::factory()->create([
            'address_line1' => '100 Main Street',
            'address_line2' => 'Suite 4',
            'city' => 'Columbus',
            'state' => 'OH',
            'postal_code' => '43210',
        ]);

        expect($organization->formattedAddress())->toBe("100 Main Street\nSuite 4\nColumbus, OH 43210");
    });

    it('skips the lines it does not have', function () {
        $organization = Organization::factory()->create([
            'address_line1' => '100 Main Street',
            'address_line2' => null,
            'city' => 'Columbus',
            'state' => 'OH',
            'postal_code' => null,
        ]);

        expect($organization->formattedAddress())->toBe("100 Main Street\nColumbus, OH");
    });

    it('returns null when there is no address at all', function () {
        $organization = Organization::factory()->create([
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
        ]);

        expect($organization->formattedAddress())->toBeNull();
    });
});
