<?php

use App\Enums\MembershipStatus;
use App\Filament\Admin\Resources\OrganizationResource\Pages\EditOrganization;
use App\Filament\Admin\Resources\OrganizationResource\Pages\ListOrganizations;
use App\Filament\Admin\Resources\OrganizationResource\RelationManagers\RepresentativesRelationManager;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;

beforeEach(function () {
    usingAdminPanel();
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

describe('the directory', function () {
    it('lists schools and finds them by name', function () {
        $kenyon = Organization::factory()->named('Kenyon College')->create();
        $rhodes = Organization::factory()->named('Rhodes College')->create();

        livewire(ListOrganizations::class)
            ->assertCanSeeTableRecords([$kenyon, $rhodes])
            ->searchTable('Kenyon')
            ->assertCanSeeTableRecords([$kenyon])
            ->assertCanNotSeeTableRecords([$rhodes]);
    });

    it('filters to schools with nobody speaking for them', function () {
        // Zero active reps is the interesting number: campaigns fall back to
        // admissions_email, or drop the school entirely.
        $orphaned = Organization::factory()->create();
        User::factory()->retiredRep($orphaned)->create();

        $staffed = Organization::factory()->create();
        User::factory()->rep($staffed)->create();

        livewire(ListOrganizations::class)
            ->filterTable('needs_a_rep')
            ->assertCanSeeTableRecords([$orphaned])
            ->assertCanNotSeeTableRecords([$staffed]);
    });

    it('filters to names that normalize the same way', function () {
        $a = Organization::factory()->named('The University of Example')->create();
        $b = Organization::factory()->named('University of Example')->create();
        $unique = Organization::factory()->named('Kenyon College')->create();

        livewire(ListOrganizations::class)
            ->filterTable('possible_duplicates')
            ->assertCanSeeTableRecords([$a, $b])
            ->assertCanNotSeeTableRecords([$unique]);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(ListOrganizations::class)->assertForbidden();
    });
});

describe('editing a profile', function () {
    it('saves the admissions contact and address', function () {
        $school = Organization::factory()->create();

        livewire(EditOrganization::class, ['record' => $school->getRouteKey()])
            ->fillForm([
                'admissions_office' => 'Office of Undergraduate Admissions',
                'admissions_email' => 'admissions@example.edu',
                'city' => 'Chattanooga',
                'state' => 'TN',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($school->refresh()->admissions_email)->toBe('admissions@example.edu')
            ->and($school->city)->toBe('Chattanooga');
    });

    it('re-derives the matching name when the school is renamed', function () {
        $school = Organization::factory()->named('Example College')->create();

        livewire(EditOrganization::class, ['record' => $school->getRouteKey()])
            ->fillForm(['name' => 'The Example University'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($school->refresh()->normalized_name)->toBe('example university');
    });
});

describe('the merge action', function () {
    it('repoints everything and removes the husk', function () {
        $keep = Organization::factory()->named('The University of Example')->create();
        $duplicate = Organization::factory()->named('University of Example')->create();

        $rep = User::factory()->rep($duplicate)->create();
        $fair = Fair::factory()->create();
        $registration = Registration::factory()->forEvent($fair)->forOrganization($duplicate)->create();
        Grant::factory()->for($duplicate)->for($fair)->create();

        livewire(ListOrganizations::class)
            ->callTableAction('merge', $duplicate, ['keep_id' => $keep->id]);

        expect($rep->refresh()->organization_id)->toBe($keep->id)
            ->and($registration->refresh()->organization_id)->toBe($keep->id)
            ->and($keep->grants()->count())->toBe(1)
            ->and(Organization::query()->find($duplicate->id))->toBeNull();
    });

    it('warns rather than silently resolving two live registrations for one fair', function () {
        // Which of two paid registrations a school keeps is a decision about
        // money, not a data-cleanup step.
        $keep = Organization::factory()->create();
        $duplicate = Organization::factory()->create();
        $fair = Fair::factory()->create();
        Registration::factory()->forEvent($fair)->forOrganization($keep)->create();
        Registration::factory()->forEvent($fair)->forOrganization($duplicate)->create();

        livewire(ListOrganizations::class)
            ->callTableAction('merge', $duplicate, ['keep_id' => $keep->id])
            ->assertNotified();

        expect($keep->registrations()->where('event_id', $fair->id)->count())->toBe(2);
    });

    it('is hidden from a user who cannot manage schools', function () {
        $school = Organization::factory()->create();
        $rep = User::factory()->rep()->create();

        expect($rep->can('merge', $school))->toBeFalse()
            ->and($this->coordinator->can('merge', $school))->toBeTrue();
    });
});

describe('deleting', function () {
    it('refuses a school with any history, because the keys cascade', function () {
        $withHistory = Organization::factory()->create();
        Registration::factory()->forOrganization($withHistory)->create();

        $empty = Organization::factory()->create();

        expect($this->coordinator->can('delete', $withHistory))->toBeFalse()
            ->and($this->coordinator->can('delete', $empty))->toBeTrue();
    });

    it('refuses a school that still has a rep pointing at it', function () {
        $school = Organization::factory()->create();
        User::factory()->rep($school)->create();

        expect($this->coordinator->can('delete', $school))->toBeFalse();
    });
});

describe('the representatives relation manager', function () {
    beforeEach(function () {
        $this->school = Organization::factory()->create();
    });

    it('approves a pending claim', function () {
        $pending = User::factory()->pendingRep($this->school)->create();

        livewire(RepresentativesRelationManager::class, [
            'ownerRecord' => $this->school,
            'pageClass' => EditOrganization::class,
        ])->callTableAction('approveClaim', $pending);

        expect($pending->refresh()->membership_status)->toBe(MembershipStatus::Active);
    });

    it('denies a claim, detaching the person so they can claim elsewhere', function () {
        $pending = User::factory()->pendingRep($this->school)->create();

        livewire(RepresentativesRelationManager::class, [
            'ownerRecord' => $this->school,
            'pageClass' => EditOrganization::class,
        ])->callTableAction('denyClaim', $pending, ['reason' => 'Not known to us.']);

        expect($pending->refresh()->organization_id)->toBeNull()
            ->and($pending->membership_status)->toBeNull();
    });

    it('retires an active rep without touching the school history', function () {
        $rep = User::factory()->rep($this->school)->create();
        Registration::factory()->forOrganization($this->school)->create(['user_id' => $rep->id]);

        livewire(RepresentativesRelationManager::class, [
            'ownerRecord' => $this->school,
            'pageClass' => EditOrganization::class,
        ])->callTableAction('retire', $rep);

        expect($rep->refresh()->membership_status)->toBe(MembershipStatus::Retired)
            ->and($this->school->registrations()->count())->toBe(1);
    });

    it('reinstates a retired rep', function () {
        $rep = User::factory()->retiredRep($this->school)->create();

        livewire(RepresentativesRelationManager::class, [
            'ownerRecord' => $this->school,
            'pageClass' => EditOrganization::class,
        ])->callTableAction('reinstate', $rep);

        expect($rep->refresh()->membership_status)->toBe(MembershipStatus::Active)
            ->and($rep->retired_at)->toBeNull();
    });

    it('offers only the actions that apply to each membership state', function () {
        $active = User::factory()->rep($this->school)->create();
        $pending = User::factory()->pendingRep($this->school)->create();
        $retired = User::factory()->retiredRep($this->school)->create();

        $manager = livewire(RepresentativesRelationManager::class, [
            'ownerRecord' => $this->school,
            'pageClass' => EditOrganization::class,
        ]);

        $manager->assertTableActionHidden('approveClaim', $active)
            ->assertTableActionVisible('approveClaim', $pending)
            ->assertTableActionVisible('retire', $active)
            ->assertTableActionHidden('retire', $retired)
            ->assertTableActionVisible('reinstate', $retired)
            ->assertTableActionHidden('reinstate', $active);
    });
});
