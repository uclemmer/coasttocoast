<?php

use App\Livewire\Staff\Interests\Index as InterestIndex;
use App\Models\Event as Fair;
use App\Models\EventInterest;
use App\Models\User;
use Livewire\Attributes\Url;
use UClemmer\LaravelCore\Admin\Permissions as AdminPermissions;
use UClemmer\LaravelCore\Auth\Role;

/*
 * The notify-me list (docs/13, doc 10 D-10-c).
 *
 * The first /staff screen with no Filament ancestor, so nothing here is a
 * port: there was no resource to carry behaviour over from. The screen reads
 * and prunes; the button that mails these people stays on the fair page, and
 * `EventsTest` still owns it.
 */

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);

    $this->fair = Fair::factory()->create();
});

describe('the list', function () {
    it('alphabetizes on the organization sort key, with unnamed signups first', function () {
        // Same key as the roster and the delivery table, so a coordinator
        // moving between the three screens meets one alphabet.
        foreach (['Vanderbilt University', 'The University of Alabama at Birmingham', 'University of Alabama'] as $name) {
            EventInterest::factory()->for($this->fair, 'event')->create(['organization_name' => $name]);
        }
        EventInterest::factory()->for($this->fair, 'event')->withoutOrganizationName()->create();

        $listed = livewire(InterestIndex::class)->instance()->interests();

        expect($listed->pluck('organization_name')->all())->toBe([
            null,
            'University of Alabama',
            'The University of Alabama at Birmingham',
            'Vanderbilt University',
        ]);
    });

    it('finds a signup by email or by organization', function () {
        EventInterest::factory()->for($this->fair, 'event')
            ->create(['email' => 'dana@kenyon.example', 'organization_name' => 'Kenyon College']);
        EventInterest::factory()->for($this->fair, 'event')
            ->create(['email' => 'sam@rhodes.example', 'organization_name' => 'Rhodes College']);

        $byEmail = livewire(InterestIndex::class)->set('search', 'kenyon.example')->instance()->interests();
        $byOrganization = livewire(InterestIndex::class)->set('search', 'Rhodes')->instance()->interests();

        expect($byEmail->pluck('email')->all())->toBe(['dana@kenyon.example'])
            ->and($byOrganization->pluck('email')->all())->toBe(['sam@rhodes.example']);
    });

    it('finds an email containing an underscore', function () {
        /*
         * Underscores are ordinary in email addresses, and this search returned
         * NOTHING for one on this app's SQLite database until 2026-09-02.
         *
         * The term was interpolated raw, so `_` reached the query as the
         * match-any-character wildcard rather than as itself. `LikeTerm` escapes
         * it and names the escape character in the same breath — both halves are
         * needed, and SQLite has no default escape character, so escaping
         * without the clause matches a literal backslash instead.
         */
        EventInterest::factory()->for($this->fair, 'event')
            ->create(['email' => 'dana_lee@kenyon.example', 'organization_name' => 'Kenyon College']);
        EventInterest::factory()->for($this->fair, 'event')
            ->create(['email' => 'danaxlee@kenyon.example', 'organization_name' => 'Rhodes College']);

        $found = livewire(InterestIndex::class)->set('search', 'dana_lee')->instance()->interests();

        // Exactly the address typed — not the one where `_` stood in for `x`.
        expect($found->pluck('email')->all())->toBe(['dana_lee@kenyon.example']);
    });

    it('filters to the people the announcement would still reach', function () {
        // The seam with the fair page's button: this filter is exactly the set
        // that `Staff\Events\Show::announce()` sends to.
        $waiting = EventInterest::factory()->for($this->fair, 'event')->create();
        EventInterest::factory()->for($this->fair, 'event')->notified()->create();

        $page = livewire(InterestIndex::class)->set('status', 'waiting');

        expect($page->instance()->interests()->pluck('id')->all())->toBe([$waiting->id])
            ->and($page->instance()->waitingCount())->toBe(1);
    });

    it('filters to one fair', function () {
        $other = Fair::factory()->create();
        $mine = EventInterest::factory()->for($this->fair, 'event')->create();
        EventInterest::factory()->for($other, 'event')->create();

        $listed = livewire(InterestIndex::class)->set('eventId', (string) $this->fair->id)->instance()->interests();

        expect($listed->pluck('id')->all())->toBe([$mine->id]);
    });

    it('counts only the rows the filters are showing as still waiting', function () {
        // The banner reports the filtered set, not the whole table — otherwise
        // it contradicts the list underneath it.
        $other = Fair::factory()->create();
        EventInterest::factory()->for($this->fair, 'event')->create();
        EventInterest::factory()->for($other, 'event')->create();

        $page = livewire(InterestIndex::class)->set('eventId', (string) $this->fair->id);

        expect($page->instance()->waitingCount())->toBe(1);
    });

    it('does not offer an announce button, which belongs to the fair page', function () {
        // Two buttons that look like they do the same thing is the failure
        // this screen was shaped to avoid.
        EventInterest::factory()->for($this->fair, 'event')->create();

        livewire(InterestIndex::class)
            ->assertDontSee('wire:click="announce"', escape: false)
            ->assertSee('Announce registration from the fair page');
    });
});

describe('the seam with the fair page', function () {
    it('reads its filters from the query string', function () {
        // The fair page's link carries both filters, so this is what makes
        // that link mean anything. Driven over HTTP rather than through the
        // component, because a query string is exactly what a link delivers.
        $waiting = EventInterest::factory()->for($this->fair, 'event')
            ->create(['organization_name' => 'Kenyon College']);
        EventInterest::factory()->for($this->fair, 'event')->notified()
            ->create(['organization_name' => 'Rhodes College']);
        EventInterest::factory()->for(Fair::factory()->create(), 'event')
            ->create(['organization_name' => 'Berry College']);

        $this->get(route('staff.interests', ['eventId' => $this->fair->id, 'status' => 'waiting']))
            ->assertOk()
            ->assertSee($waiting->email)
            ->assertDontSee('Rhodes College')
            ->assertDontSee('Berry College');
    });

    it('links from the fair page to exactly the set the announcement would mail', function () {
        // Without the URL bindings this link would land on the unfiltered list
        // and quietly show the wrong thing, so the two are one feature.
        EventInterest::factory()->for($this->fair, 'event')->create();

        // Asserted through the default escaping, not around it: the href is
        // written by `{{ }}`, so the `&` between the two parameters is
        // `&amp;` on the page and a raw-string match would never fire.
        $expected = route('staff.interests', ['eventId' => $this->fair->id, 'status' => 'waiting']);

        $this->get(route('staff.events.show', $this->fair))
            ->assertOk()
            ->assertSee($expected);
    });

    it('declares every filter as droppable from the query string', function () {
        /*
         * Without `except: ''` Livewire keeps the key and empties it, so
         * arriving from the fair page and switching back to "all fairs" left
         * `?eventId=` in the address bar — a filter that reads as set and is
         * not. Found in a browser pass.
         *
         * Asserted through reflection, which is not the usual shape and is the
         * honest one here: Livewire 4 applies `#[Url]` entirely in the browser.
         * There is no URL in the effects, none in the snapshot memo, and
         * `Testable` has no query-string assertion — both were checked before
         * settling for this. The attribute is the only part of the behaviour a
         * server-side test can see, and it is also the part somebody would
         * delete.
         */
        $component = new ReflectionClass(InterestIndex::class);

        foreach (['search', 'eventId', 'status'] as $filter) {
            $attributes = $component->getProperty($filter)->getAttributes(Url::class);

            expect($attributes)->toHaveCount(1, "{$filter} is not URL-bound.")
                ->and($attributes[0]->newInstance()->except)->toBe('', "{$filter} keeps an empty key in the URL.");
        }
    });

    it('links back to the fair page once a single fair is chosen', function () {
        EventInterest::factory()->for($this->fair, 'event')->create();

        livewire(InterestIndex::class)
            ->set('eventId', (string) $this->fair->id)
            ->assertSee(route('staff.events.show', $this->fair), escape: false);
    });

    it('does not guess a fair page while showing every fair', function () {
        // The announcement is an action on one fair, so "all fairs" has no
        // destination. Prose, not a link that picks one.
        EventInterest::factory()->for($this->fair, 'event')->create();
        EventInterest::factory()->for(Fair::factory()->create(), 'event')->create();

        livewire(InterestIndex::class)
            ->assertDontSee(route('staff.events.show', $this->fair), escape: false)
            ->assertSee('Announce registration from the fair page');
    });
});

describe('pruning', function () {
    it('removes a junk signup', function () {
        $interest = EventInterest::factory()->for($this->fair, 'event')->create();

        livewire(InterestIndex::class)
            ->call('confirmDelete', $interest->id)
            ->call('delete');

        expect(EventInterest::query()->whereKey($interest->id)->exists())->toBeFalse();
    });

    it('removes a ticked batch', function () {
        $spam = EventInterest::factory()->for($this->fair, 'event')->count(2)->create();
        $keep = EventInterest::factory()->for($this->fair, 'event')->create();

        livewire(InterestIndex::class)
            ->set('selected', $spam->pluck('id')->map(fn (int $id): string => (string) $id)->all())
            ->call('deleteSelected');

        expect(EventInterest::query()->pluck('id')->all())->toBe([$keep->id]);
    });

    it('drops the selection when a filter changes', function () {
        // The ids are from the previous result set; keeping them would let a
        // bulk delete reach rows the user can no longer see.
        $interest = EventInterest::factory()->for($this->fair, 'event')->create();

        $page = livewire(InterestIndex::class)
            ->set('selected', [(string) $interest->id])
            ->set('status', 'notified');

        expect($page->get('selected'))->toBe([]);
    });
});

describe('permission', function () {
    it('keeps a representative out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(InterestIndex::class)->assertForbidden();
    });

    it('is not opened by admin.access alone', function () {
        // `abortUnlessStaff()` and the policy are two different gates, and the
        // screen must ask both. Somebody who may be in /staff at all but does
        // not manage fairs has no business reading the lead list.
        $role = Role::query()->create([
            'name' => 'greeter',
            'label' => 'Greeter',
            'description' => 'Staff, but not fairs.',
        ]);
        $role->givePermissionTo(AdminPermissions::ACCESS);

        $staff = User::factory()->create();
        $staff->assignRole($role)->forgetCoreRoleCache();

        $this->actingAs($staff);

        livewire(InterestIndex::class)->assertForbidden();
    });

    it('refuses the prune to anyone the policy refuses', function () {
        // The screen and the prune ask the same permission, so this is one
        // gate rather than the visibility-versus-authorization split the
        // package ports hit. `delete()` still authorises for itself, so
        // loosening `viewAny` later cannot open the prune by accident.
        $interest = EventInterest::factory()->for($this->fair, 'event')->create();

        expect(rep()->can('delete', $interest))->toBeFalse()
            ->and($this->coordinator->can('delete', $interest))->toBeTrue();
    });
});
