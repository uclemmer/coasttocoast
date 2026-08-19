<?php

use App\Enums\MembershipStatus;
use App\Events\MembershipApproved;
use App\Events\MembershipDenied;
use App\Events\MembershipRetired;
use App\Exceptions\MembershipNotAllowed;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(OrganizationService::class);
    $this->school = Organization::factory()->create();
    $this->coordinator = User::factory()->coordinator()->create();
});

describe('approving a claim', function () {
    it('makes a pending rep active', function () {
        $rep = User::factory()->pendingRep($this->school)->create();

        $this->service->approveClaim($rep, $this->coordinator);

        expect($rep->refresh()->membership_status)->toBe(MembershipStatus::Active)
            ->and($rep->membership_approved_at)->not->toBeNull()
            ->and($rep->actsForOrganization())->toBeTrue();
    });

    it('fires the event so the rep can be told', function () {
        Event::fake([MembershipApproved::class]);
        $rep = User::factory()->pendingRep($this->school)->create();

        $this->service->approveClaim($rep, $this->coordinator);

        Event::assertDispatched(MembershipApproved::class);
    });

    it('refuses a rep who is not pending', function (string $state) {
        $rep = User::factory()->{$state}($this->school)->create();

        expect(fn () => $this->service->approveClaim($rep, $this->coordinator))
            ->toThrow(MembershipNotAllowed::class, 'already been decided');
    })->with(['rep', 'retiredRep']);
});

describe('denying a claim', function () {
    it('detaches the rep from the school entirely', function () {
        // Not left pending forever and not deleted: someone who claimed the
        // wrong school should be able to sign up again for the right one,
        // which a lingering pending membership would block.
        $rep = User::factory()->pendingRep($this->school)->create();

        $this->service->denyClaim($rep, $this->coordinator, 'We do not recognise this person.');

        expect($rep->refresh()->organization_id)->toBeNull()
            ->and($rep->membership_status)->toBeNull()
            ->and(User::query()->find($rep->id))->not->toBeNull();
    });

    it('passes the school and reason to the event, since the rep no longer points at it', function () {
        Event::fake([MembershipDenied::class]);
        $rep = User::factory()->pendingRep($this->school)->create();

        $this->service->denyClaim($rep, $this->coordinator, 'Unknown to us.');

        Event::assertDispatched(
            MembershipDenied::class,
            fn (MembershipDenied $e): bool => $e->organization?->is($this->school) === true
                && $e->reason === 'Unknown to us.',
        );
    });

    it('refuses a rep who is not pending', function () {
        $rep = User::factory()->rep($this->school)->create();

        expect(fn () => $this->service->denyClaim($rep, $this->coordinator))
            ->toThrow(MembershipNotAllowed::class);
    });
});

describe('retiring', function () {
    it('revokes org rights while keeping the account and the history', function () {
        $rep = User::factory()->rep($this->school)->create();
        Registration::factory()->create(['user_id' => $rep->id, 'organization_id' => $this->school->id]);

        $this->service->retire($rep, $this->coordinator);

        expect($rep->refresh()->membership_status)->toBe(MembershipStatus::Retired)
            ->and($rep->actsForOrganization())->toBeFalse()
            ->and($rep->retired_by)->toBe($this->coordinator->id)
            // The account and its history survive — the school's registrations
            // were never the rep's to take with them.
            ->and($rep->organization_id)->toBe($this->school->id)
            ->and($rep->registrations()->count())->toBe(1)
            ->and($this->school->registrations()->count())->toBe(1);
    });

    it('distinguishes self-retirement from a coordinator retiring someone', function () {
        // The two want different emails: a confirmation versus an explanation.
        Event::fake([MembershipRetired::class]);
        $rep = User::factory()->rep($this->school)->create();

        $this->service->retire($rep, $rep);

        Event::assertDispatched(MembershipRetired::class, fn (MembershipRetired $e): bool => $e->selfService);

        $other = User::factory()->rep($this->school)->create();
        $this->service->retire($other, $this->coordinator);

        Event::assertDispatched(
            MembershipRetired::class,
            fn (MembershipRetired $e): bool => $e->rep->is($other) && ! $e->selfService,
        );
    });

    it('takes a retired rep out of the active-rep set campaigns mail', function () {
        $rep = User::factory()->rep($this->school)->create();

        $this->service->retire($rep, $this->coordinator);

        expect($this->school->activeReps()->count())->toBe(0);
    });

    it('refuses somebody who is not a member', function () {
        expect(fn () => $this->service->retire($this->coordinator, $this->coordinator))
            ->toThrow(MembershipNotAllowed::class, 'not currently a representative');
    });

    it('refuses to retire someone twice', function () {
        $rep = User::factory()->retiredRep($this->school)->create();

        expect(fn () => $this->service->retire($rep, $this->coordinator))
            ->toThrow(MembershipNotAllowed::class);
    });
});

describe('reinstating', function () {
    it('brings a retired rep back', function () {
        $rep = User::factory()->retiredRep($this->school)->create();

        $this->service->reinstate($rep, $this->coordinator);

        expect($rep->refresh()->membership_status)->toBe(MembershipStatus::Active)
            ->and($rep->retired_at)->toBeNull()
            ->and($rep->retired_by)->toBeNull();
    });

    it('refuses somebody who has not retired', function () {
        $rep = User::factory()->rep($this->school)->create();

        expect(fn () => $this->service->reinstate($rep, $this->coordinator))
            ->toThrow(MembershipNotAllowed::class, 'has not retired');
    });
});

describe('merging duplicates', function () {
    beforeEach(function () {
        $this->keep = Organization::factory()->named('The University of Example')->create([
            'website' => 'https://example.edu',
            'admissions_email' => null,
        ]);
        $this->duplicate = Organization::factory()->named('University of Example')->create([
            'website' => 'https://duplicate.example.edu',
            'admissions_email' => 'admissions@example.edu',
        ]);
    });

    it('repoints reps, registrations and grants, then deletes the husk', function () {
        $rep = User::factory()->rep($this->duplicate)->create();
        $fair = Fair::factory()->create();
        $registration = Registration::factory()->forEvent($fair)->forOrganization($this->duplicate)->create();
        $grant = Grant::factory()->for($this->duplicate)->for($fair)->create();

        $this->service->merge($this->duplicate, $this->keep);

        expect($rep->refresh()->organization_id)->toBe($this->keep->id)
            ->and($registration->refresh()->organization_id)->toBe($this->keep->id)
            ->and($grant->refresh()->organization_id)->toBe($this->keep->id)
            ->and(Organization::query()->find($this->duplicate->id))->toBeNull();
    });

    it('does not take the history with it when the husk is deleted', function () {
        // The foreign keys cascade. Deleting first would destroy exactly the
        // history the merge exists to preserve.
        $fair = Fair::factory()->create();
        Registration::factory()->count(3)->forEvent($fair)->forOrganization($this->duplicate)->create();

        $this->service->merge($this->duplicate, $this->keep);

        expect(Registration::query()->count())->toBe(3)
            ->and($this->keep->registrations()->count())->toBe(3);
    });

    it('fills gaps in the survivor without overwriting what it already has', function () {
        $this->service->merge($this->duplicate, $this->keep);

        expect($this->keep->refresh()->admissions_email)->toBe('admissions@example.edu')
            // Somebody deliberately entered this. A merge must not silently
            // replace it.
            ->and($this->keep->website)->toBe('https://example.edu');
    });

    it('reports registrations that now collide on the same fair rather than resolving them', function () {
        // Which of two registrations a school keeps is a judgement about
        // money, not a data-cleanup step.
        $fair = Fair::factory()->create();
        Registration::factory()->forEvent($fair)->forOrganization($this->keep)->create();
        $doomed = Registration::factory()->forEvent($fair)->forOrganization($this->duplicate)->create();

        $collisions = $this->service->merge($this->duplicate, $this->keep);

        expect($collisions)->toHaveCount(1)
            ->and($collisions[0]->id)->toBe($doomed->id)
            // Both survive. The coordinator decides.
            ->and($this->keep->registrations()->where('event_id', $fair->id)->count())->toBe(2);
    });

    it('ignores a cancelled registration when looking for collisions', function () {
        $fair = Fair::factory()->create();
        Registration::factory()->cancelled()->forEvent($fair)->forOrganization($this->keep)->create();
        Registration::factory()->forEvent($fair)->forOrganization($this->duplicate)->create();

        expect($this->service->merge($this->duplicate, $this->keep))->toBeEmpty();
    });

    it('refuses to merge a school into itself', function () {
        expect(fn () => $this->service->merge($this->keep, $this->keep))
            ->toThrow(MembershipNotAllowed::class, 'different school');
    });
});
