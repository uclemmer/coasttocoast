<?php

use App\Enums\MembershipStatus;
use App\Livewire\Staff\Organizations\Edit as EditOrganization;
use App\Livewire\Staff\Organizations\Index as OrganizationIndex;
use App\Livewire\Staff\Organizations\Show as ShowOrganization;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;

/*
 * The staff organization screens (docs/13).
 *
 * Ported from OrganizationResourceTest. The merge tests are the ones that
 * matter: a merge repoints real financial history, and the collision case is
 * deliberately left for a person to resolve.
 */

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

describe('the directory', function () {
    it('lists organizations and finds them by name', function () {
        Organization::factory()->named('Baylor School')->create();
        Organization::factory()->named('McCallie School')->create();

        $found = livewire(OrganizationIndex::class)->set('search', 'Baylor')->instance()->organizations();

        expect($found->pluck('name')->all())->toBe(['Baylor School']);
    });

    it('alphabetizes on the sort key, so a leading "The" does not misfile an organization', function () {
        // Ordering by `name` filed Birmingham under T, three letters away from
        // the two campuses it belongs beside.
        Organization::factory()->named('Vanderbilt University')->create();
        Organization::factory()->named('The University of Alabama at Birmingham')->create();
        Organization::factory()->named('University of Alabama')->create();

        $listed = livewire(OrganizationIndex::class)->instance()->organizations();

        expect($listed->pluck('name')->all())->toBe([
            'University of Alabama',
            'The University of Alabama at Birmingham',
            'Vanderbilt University',
        ]);
    });

    it('filters to organizations with nobody speaking for them', function () {
        // Zero active reps means campaigns fall back to admissions_email, or
        // drop the organization entirely.
        $orphan = Organization::factory()->create();
        $staffed = Organization::factory()->create();
        User::factory()->rep($staffed)->create();

        $listed = livewire(OrganizationIndex::class)->set('filter', 'needs_a_rep')->instance()->organizations();

        expect($listed->pluck('id')->all())->toContain($orphan->id)->not->toContain($staffed->id);
    });

    it('filters to names that normalize the same way', function () {
        $a = Organization::factory()->named('The University of Example')->create();
        $b = Organization::factory()->named('University of Example')->create();
        $unique = Organization::factory()->named('Somewhere Else Entirely')->create();

        $listed = livewire(OrganizationIndex::class)
            ->set('filter', 'possible_duplicates')
            ->instance()
            ->organizations();

        expect($listed->pluck('id')->all())
            ->toContain($a->id)->toContain($b->id)->not->toContain($unique->id);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(OrganizationIndex::class)->assertForbidden();
    });
});

describe('editing a profile', function () {
    it('saves the admissions contact and address', function () {
        $organization = Organization::factory()->create();

        livewire(EditOrganization::class, ['organization' => $organization])
            ->set('admissions_office', 'Office of Admission')
            ->set('admissions_email', 'admission@example.edu')
            ->set('admissions_phone', '423-555-0100')
            ->set('city', 'Chattanooga')
            ->set('state', 'TN')
            ->call('save')
            ->assertHasNoErrors();

        expect($organization->refresh())
            ->admissions_office->toBe('Office of Admission')
            ->admissions_email->toBe('admission@example.edu')
            ->city->toBe('Chattanooga');
    });

    it('re-derives the matching name when the organization is renamed', function () {
        // The normalised form is what duplicate detection compares, so it has
        // to follow a rename rather than keeping the name it was created with.
        $organization = Organization::factory()->named('Example College')->create();

        livewire(EditOrganization::class, ['organization' => $organization])
            ->set('name', 'The Example University')
            ->call('save')
            ->assertHasNoErrors();

        expect($organization->refresh()->normalized_name)->toBe('example university');
    });

    /*
     * Surfaced, not blocking (R2.7). "Boston University" and "Boston College"
     * normalize differently on purpose, so a match is worth a second look
     * rather than a veto.
     */
    it('warns about a possible duplicate while typing, without refusing the save', function () {
        Organization::factory()->named('University of Example')->create();

        $page = livewire(EditOrganization::class)->set('name', 'The University of Example');

        expect($page->instance()->possibleDuplicates())->not->toBeEmpty();

        $page->call('save')->assertHasNoErrors();

        expect(Organization::query()->where('name', 'The University of Example')->exists())->toBeTrue();
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(EditOrganization::class)->assertForbidden();
    });
});

describe('the merge action', function () {
    it('offers merge as a row action', function () {
        /*
         * The label is asserted because it is the only thing tying the row
         * button to the action: every test below reaches `startMerge` by
         * method name, so renaming the button breaks nothing else.
         *
         * Matched as markup, not as text. A plain assertDontSee('Merge into')
         * fails on the delete dialog, which says "Merge into another
         * organization instead if this is a duplicate" — prose that is meant to
         * be there. The old button label carried an ellipsis; that is what must
         * not come back.
         */
        Organization::factory()->named('University of Example')->create();

        livewire(OrganizationIndex::class)
            ->assertSeeHtml('>Merge</button>')
            ->assertDontSee('Merge into…');
    });

    it('repoints everything and removes the husk', function () {
        $keep = Organization::factory()->named('The University of Example')->create();
        $duplicate = Organization::factory()->named('University of Example')->create();

        $rep = User::factory()->rep($duplicate)->create();
        $fair = Fair::factory()->create();
        $registration = Registration::factory()->forEvent($fair)->forOrganization($duplicate)->create();
        Grant::factory()->for($duplicate)->for($fair)->create();

        livewire(OrganizationIndex::class)
            ->call('startMerge', $duplicate->id)
            ->set('keepId', (string) $keep->id)
            ->call('merge');

        expect($rep->refresh()->organization_id)->toBe($keep->id)
            ->and($registration->refresh()->organization_id)->toBe($keep->id)
            ->and($keep->grants()->count())->toBe(1)
            ->and(Organization::query()->find($duplicate->id))->toBeNull();
    });

    /*
     * Which of two paid registrations an organization keeps is a decision about money,
     * not a data-cleanup step. Filament raised a PERSISTENT notification for
     * this; a toast auto-dismisses, so the rebuild keeps it on the page until
     * somebody dismisses it by hand.
     */
    it('warns rather than silently resolving two live registrations for one fair', function () {
        $keep = Organization::factory()->create();
        $duplicate = Organization::factory()->create();
        $fair = Fair::factory()->create();
        Registration::factory()->forEvent($fair)->forOrganization($keep)->create();
        Registration::factory()->forEvent($fair)->forOrganization($duplicate)->create();

        $page = livewire(OrganizationIndex::class)
            ->call('startMerge', $duplicate->id)
            ->set('keepId', (string) $keep->id)
            ->call('merge');

        expect($page->get('collisions'))->not->toBeEmpty();
        expect($keep->registrations()->where('event_id', $fair->id)->count())->toBe(2);

        // And it survives the next interaction, which is the whole point.
        $page->set('search', 'anything');
        expect($page->get('collisions'))->not->toBeEmpty();
    });

    it('says nothing when there is nothing to resolve', function () {
        $keep = Organization::factory()->create();
        $duplicate = Organization::factory()->create();

        $page = livewire(OrganizationIndex::class)
            ->call('startMerge', $duplicate->id)
            ->set('keepId', (string) $keep->id)
            ->call('merge');

        expect($page->get('collisions'))->toBe([]);
    });

    it('refuses to merge an organization into itself', function () {
        // Refused by the service, and its message is surfaced rather than
        // replaced — so there is no second copy of the rule here to drift.
        $organization = Organization::factory()->create();

        livewire(OrganizationIndex::class)
            ->call('startMerge', $organization->id)
            ->set('keepId', (string) $organization->id)
            ->call('merge')
            ->assertDispatched('ui-toast', fn (string $e, array $p): bool => $p['variant'] === 'danger');

        expect(Organization::query()->whereKey($organization->id)->exists())->toBeTrue();
    });

    it('is refused for a user who cannot manage organizations', function () {
        $organization = Organization::factory()->create();
        $rep = User::factory()->rep()->create();

        expect($rep->can('merge', $organization))->toBeFalse()
            ->and($this->coordinator->can('merge', $organization))->toBeTrue();
    });
});

describe('deleting', function () {
    it('refuses an organization with any history, because the keys cascade', function () {
        $withHistory = Organization::factory()->create();
        Registration::factory()->forOrganization($withHistory)->create();

        livewire(OrganizationIndex::class)
            ->call('confirmDelete', $withHistory->id)
            ->call('delete')
            ->assertForbidden();

        expect(Organization::query()->whereKey($withHistory->id)->exists())->toBeTrue();
    });

    it('deletes an empty one', function () {
        $empty = Organization::factory()->create();

        livewire(OrganizationIndex::class)->call('confirmDelete', $empty->id)->call('delete');

        expect(Organization::query()->whereKey($empty->id)->exists())->toBeFalse();
    });
});

describe('the representatives list', function () {
    beforeEach(function () {
        $this->organization = Organization::factory()->create();
    });

    it('approves a pending claim', function () {
        $pending = User::factory()->pendingRep($this->organization)->create();

        livewire(ShowOrganization::class, ['organization' => $this->organization])
            ->call('approveClaim', $pending->id);

        expect($pending->refresh()->membership_status)->toBe(MembershipStatus::Active);
    });

    it('denies a claim, detaching the person so they can claim elsewhere', function () {
        $pending = User::factory()->pendingRep($this->organization)->create();

        livewire(ShowOrganization::class, ['organization' => $this->organization])
            ->call('startDeny', $pending->id)
            ->set('reason', 'Not on the staff list we were sent.')
            ->call('denyClaim');

        expect($pending->refresh()->organization_id)->toBeNull();
    });

    it('retires an active rep without touching the organization history', function () {
        $rep = User::factory()->rep($this->organization)->create();
        Registration::factory()->forOrganization($this->organization)->create(['user_id' => $rep->id]);

        livewire(ShowOrganization::class, ['organization' => $this->organization])
            ->call('startRetire', $rep->id)
            ->call('retire');

        expect($rep->refresh()->isRetired())->toBeTrue()
            ->and($this->organization->registrations()->count())->toBe(1);
    });

    it('reinstates a retired rep', function () {
        $rep = User::factory()->retiredRep($this->organization)->create();

        livewire(ShowOrganization::class, ['organization' => $this->organization])->call('reinstate', $rep->id);

        expect($rep->refresh()->membership_status)->toBe(MembershipStatus::Active);
    });

    /*
     * The id arrives from the browser. Without scoping to this organization, a
     * crafted one would retire somebody at a different organization entirely — the
     * relation manager scoped to its owner for us.
     */
    it('refuses a representative belonging to another organization', function () {
        $elsewhere = User::factory()->rep(Organization::factory()->create())->create();

        livewire(ShowOrganization::class, ['organization' => $this->organization])
            ->call('startRetire', $elsewhere->id)
            ->call('retire');

        expect($elsewhere->refresh()->isRetired())->toBeFalse();
    });

    it('surfaces the service refusal rather than a generic failure', function () {
        // Retiring somebody who is already retired.
        $rep = User::factory()->retiredRep($this->organization)->create();

        livewire(ShowOrganization::class, ['organization' => $this->organization])
            ->call('startRetire', $rep->id)
            ->call('retire')
            ->assertDispatched('ui-toast', fn (string $event, array $params): bool => $params['variant'] === 'danger');
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(ShowOrganization::class, ['organization' => $this->organization])->assertForbidden();
    });
});
