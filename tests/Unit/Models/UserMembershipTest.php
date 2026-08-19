<?php

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('membership casts', function () {
    it('casts the status enum and the membership timestamps', function () {
        $rep = User::factory()->rep()->create();

        expect($rep->membership_status)->toBe(MembershipStatus::Active)
            ->and($rep->membership_approved_at)->toBeInstanceOf(Carbon::class)
            ->and($rep->retired_at)->toBeNull();
    });

    it('leaves a coordinator with no organization and no membership status', function () {
        // A null status means "not a representative", not "status unknown".
        $coordinator = User::factory()->coordinator()->create();

        expect($coordinator->organization_id)->toBeNull()
            ->and($coordinator->membership_status)->toBeNull();
    });
});

describe('acting for an organization', function () {
    it('lets only active members act', function (string $state, bool $canAct) {
        $user = User::factory()->{$state}()->create();

        expect($user->actsForOrganization())->toBe($canAct);
    })->with([
        'active rep' => ['rep', true],
        'pending rep' => ['pendingRep', false],
        'retired rep' => ['retiredRep', false],
        'coordinator' => ['coordinator', false],
    ]);

    it('does not let a status without an organization act', function () {
        // Defensive: a membership status on a user with no organization is a
        // data fault, and it must not read as permission.
        $orphan = User::factory()->create([
            'organization_id' => null,
            'membership_status' => MembershipStatus::Active,
        ]);

        expect($orphan->actsForOrganization())->toBeFalse();
    });

    it('identifies pending and retired members', function () {
        expect(User::factory()->pendingRep()->create()->isPendingApproval())->toBeTrue()
            ->and(User::factory()->retiredRep()->create()->isRetired())->toBeTrue()
            ->and(User::factory()->rep()->create()->isPendingApproval())->toBeFalse()
            ->and(User::factory()->rep()->create()->isRetired())->toBeFalse();
    });
});

describe('relationships and scopes', function () {
    it('resolves the organization and the registrations this person submitted', function () {
        $organization = Organization::factory()->create();
        $rep = User::factory()->rep($organization)->create();
        Registration::factory()->count(2)->create(['user_id' => $rep->id]);

        expect($rep->organization->is($organization))->toBeTrue()
            ->and($rep->registrations()->count())->toBe(2);
    });

    it('records who retired a rep', function () {
        $coordinator = User::factory()->coordinator()->create();
        $rep = User::factory()->retiredRep()->create(['retired_by' => $coordinator->id]);

        expect($rep->retiredBy->is($coordinator))->toBeTrue();
    });

    it('scopes active and pending reps, excluding coordinators', function () {
        $organization = Organization::factory()->create();
        $active = User::factory()->rep($organization)->create();
        $pending = User::factory()->pendingRep($organization)->create();
        User::factory()->retiredRep($organization)->create();
        User::factory()->coordinator()->create();

        expect(User::query()->activeReps()->pluck('id')->all())->toBe([$active->id])
            ->and(User::query()->pendingReps()->pluck('id')->all())->toBe([$pending->id]);
    });

    it('scopes to reps who can actually receive an SMS', function () {
        $reachable = User::factory()->smsOptedIn()->create();
        User::factory()->create(['phone' => '+15551234567', 'sms_opt_in' => false]);
        User::factory()->create(['phone' => null, 'sms_opt_in' => true]);

        expect(User::query()->smsReachable()->pluck('id')->all())->toBe([$reachable->id]);
    });
});
