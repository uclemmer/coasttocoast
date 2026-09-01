<?php

use App\Livewire\Staff\Events\Edit as EditEvent;
use App\Livewire\Staff\Events\Index as EventIndex;
use App\Livewire\Staff\Events\Show as ShowEvent;
use App\Models\Event;
use App\Models\EventInterest;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationOpenAnnouncement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/*
 * The staff fair screens (docs/13).
 *
 * Ported from EventResourceTest. The money tests matter most: the fee is typed
 * in dollars and stored in cents, and the Filament version put that conversion
 * on the field precisely because doing it anywhere else had once saved every
 * fair at zero. Every assertion below is on the stored integer.
 */

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

/** The fields a fair cannot be saved without. */
function validFair(array $overrides = []): array
{
    return array_merge([
        'name' => 'College Fair 2028',
        'slug' => 'college-fair-2028',
        'venue_name' => 'Chattanooga Convention & Trade Center',
        'venue_address' => "1150 Carter Street\nChattanooga, TN 37402",
        'starts_at' => '2028-04-18T18:30',
        'ends_at' => '2028-04-18T20:00',
        'priceDollars' => '215.00',
    ], $overrides);
}

describe('listing', function () {
    it('shows every fair, published or not', function () {
        $published = Event::factory()->create(['is_published' => true]);
        $draft = Event::factory()->create(['is_published' => false]);

        $listed = livewire(EventIndex::class)->instance()->events()->pluck('id')->all();

        expect($listed)->toContain($published->id)->toContain($draft->id);
    });

    it('filters to published fairs', function () {
        $published = Event::factory()->create(['is_published' => true]);
        Event::factory()->create(['is_published' => false]);

        $listed = livewire(EventIndex::class)->set('published', 'yes')->instance()->events();

        expect($listed->pluck('id')->all())->toBe([$published->id]);
    });

    it('reports the three registration states the public page branches on', function () {
        // One expression, so the staff table and the public call-to-action
        // cannot disagree about which state a fair is in.
        Carbon::setTestNow('2027-02-01 12:00');

        $open = Event::factory()->registrationOpen()->create();
        $notYet = Event::factory()->registrationNotYetOpen()->create();
        $closed = Event::factory()->registrationClosed()->create();

        $page = livewire(EventIndex::class)->instance();

        expect($page->registrationState($open))->toBe('Open')
            ->and($page->registrationState($notYet))->toBe('Not yet open')
            ->and($page->registrationState($closed))->toBe('Closed');

        Carbon::setTestNow();
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(EventIndex::class)->assertForbidden();
    });
});

describe('creating', function () {
    it('creates a fair, converting the fee from dollars to cents', function () {
        $page = livewire(EditEvent::class);

        foreach (validFair(['is_published' => true]) as $field => $value) {
            $page->set($field, $value);
        }

        $page->call('save')->assertHasNoErrors();

        expect(Event::query()->where('slug', 'college-fair-2028')->first())
            ->price_cents->toBe(21500)
            ->is_published->toBeTrue();
    });

    it('rounds a fractional fee instead of truncating it', function () {
        // 215.10 * 100 is 21509.999999999996 in IEEE 754. Casting that to int
        // charges an organization a cent less than it agreed to, silently, forever.
        $page = livewire(EditEvent::class);

        foreach (validFair(['slug' => 'awkward-fee-fair', 'priceDollars' => '215.10']) as $field => $value) {
            $page->set($field, $value);
        }

        $page->call('save')->assertHasNoErrors();

        expect(Event::query()->where('slug', 'awkward-fee-fair')->first()->price_cents)->toBe(21510);
    });

    it('suggests a slug from the name while creating', function () {
        expect(livewire(EditEvent::class)->set('name', 'College Fair 2029')->get('slug'))
            ->toBe('college-fair-2029');
    });

    it('requires the fair to have a name, venue and dates', function () {
        livewire(EditEvent::class)->call('save')
            ->assertHasErrors(['name', 'slug', 'venue_name', 'venue_address', 'starts_at', 'ends_at', 'priceDollars']);
    });

    it('refuses a fair that ends before it starts', function () {
        $page = livewire(EditEvent::class);

        foreach (validFair(['starts_at' => '2028-04-18T20:00', 'ends_at' => '2028-04-18T18:30']) as $f => $v) {
            $page->set($f, $v);
        }

        $page->call('save')->assertHasErrors(['ends_at']);
    });

    it('refuses a registration window that closes before it opens', function () {
        $page = livewire(EditEvent::class);

        foreach (validFair([
            'registration_opens_at' => '2028-03-01T09:00',
            'registration_closes_at' => '2028-02-01T09:00',
        ]) as $f => $v) {
            $page->set($f, $v);
        }

        $page->call('save')->assertHasErrors(['registration_closes_at']);
    });

    it('refuses a negative fee', function () {
        $page = livewire(EditEvent::class);

        foreach (validFair(['priceDollars' => '-5']) as $f => $v) {
            $page->set($f, $v);
        }

        $page->call('save')->assertHasErrors(['priceDollars']);
    });

    it('refuses a duplicate slug', function () {
        Event::factory()->create(['slug' => 'taken']);

        $page = livewire(EditEvent::class);

        foreach (validFair(['slug' => 'taken']) as $f => $v) {
            $page->set($f, $v);
        }

        $page->call('save')->assertHasErrors(['slug']);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(EditEvent::class)->assertForbidden();
    });
});

describe('editing', function () {
    it('shows the stored fee in dollars and saves it back as cents', function () {
        // Both directions, in one test, because getting one right and the
        // other wrong is the failure this convention exists to prevent.
        $event = Event::factory()->priced(21500)->create();

        $page = livewire(EditEvent::class, ['event' => $event]);

        expect($page->get('priceDollars'))->toBe('215.00');

        $page->set('priceDollars', '225')->call('save')->assertHasNoErrors();

        expect($event->refresh()->price_cents)->toBe(22500);
    });

    it('does not rewrite the slug when the name changes on an existing fair', function () {
        // Existing links are already out in the world. Renaming a fair must
        // not break them.
        $event = Event::factory()->create(['name' => 'College Fair 2027', 'slug' => 'college-fair-2027']);

        expect(livewire(EditEvent::class, ['event' => $event])
            ->set('name', 'Coast to Coast College Fair 2027')
            ->get('slug'))->toBe('college-fair-2027');
    });

    it('publishes a fair', function () {
        $event = Event::factory()->create(['is_published' => false]);

        livewire(EditEvent::class, ['event' => $event])
            ->set('is_published', true)
            ->call('save')
            ->assertHasNoErrors();

        expect($event->refresh()->is_published)->toBeTrue();
    });

    it('allows an open-ended registration window', function () {
        // Null on either side means no bound in that direction (R1.8).
        $event = Event::factory()->registrationOpen()->create();

        livewire(EditEvent::class, ['event' => $event])
            ->set('registration_opens_at', '')
            ->set('registration_closes_at', '')
            ->call('save')
            ->assertHasNoErrors();

        expect($event->refresh()->registration_opens_at)->toBeNull()
            ->and($event->registration_closes_at)->toBeNull();
    });
});

describe('deleting', function () {
    it('refuses to delete a fair that has registrations against it', function () {
        // The foreign keys cascade — deleting the fair would take real
        // financial history with it. Unpublish instead.
        $event = Event::factory()->create();
        Registration::factory()->forEvent($event)->create();

        livewire(EventIndex::class)->call('confirmDelete', $event->id)->call('delete')->assertForbidden();

        expect(Event::query()->whereKey($event->id)->exists())->toBeTrue();
    });

    it('deletes one nobody has registered for', function () {
        $event = Event::factory()->create();

        livewire(EventIndex::class)->call('confirmDelete', $event->id)->call('delete');

        expect(Event::query()->whereKey($event->id)->exists())->toBeFalse();
    });
});

describe('announcing that registration is open', function () {
    beforeEach(function () {
        Notification::fake();
        $this->fair = Event::factory()->create(['is_published' => true]);
    });

    it('mails everyone waiting and stamps them as it goes', function () {
        EventInterest::factory()->count(2)->for($this->fair)->create(['notified_at' => null]);

        livewire(ShowEvent::class, ['event' => $this->fair])->call('announce');

        Notification::assertSentTimes(RegistrationOpenAnnouncement::class, 2);
        expect($this->fair->interests()->unnotified()->count())->toBe(0);
    });

    /*
     * The realistic mistake is a coordinator who is not sure whether the first
     * press worked. The answer to that should be "press it again", not a
     * hundred duplicate emails.
     */
    it('is safe to press twice', function () {
        EventInterest::factory()->for($this->fair)->create(['notified_at' => null]);

        $page = livewire(ShowEvent::class, ['event' => $this->fair])->call('announce');
        $page->call('announce');

        Notification::assertSentTimes(RegistrationOpenAnnouncement::class, 1);
    });

    it('skips anyone already told', function () {
        EventInterest::factory()->for($this->fair)->create(['notified_at' => now()]);
        EventInterest::factory()->for($this->fair)->create(['notified_at' => null]);

        livewire(ShowEvent::class, ['event' => $this->fair])->call('announce');

        Notification::assertSentTimes(RegistrationOpenAnnouncement::class, 1);
    });

    it('is not offered for a draft fair, and refuses if reached anyway', function () {
        // An unpublished fair has nothing for these people to register for.
        $draft = Event::factory()->create(['is_published' => false]);
        EventInterest::factory()->for($draft)->create(['notified_at' => null]);

        $page = livewire(ShowEvent::class, ['event' => $draft]);

        expect($page->instance()->canAnnounce())->toBeFalse();

        $page->call('announce');

        Notification::assertNothingSent();
    });

    it('is not offered when nobody is waiting', function () {
        expect(livewire(ShowEvent::class, ['event' => $this->fair])->instance()->canAnnounce())->toBeFalse();
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(ShowEvent::class, ['event' => $this->fair])->assertForbidden();
    });
});

describe('publishing a fair that still has its placeholder name', function () {
    // doc 10, D-9-e. The public roster renders the active fair's name as its
    // heading, so publishing without renaming puts "TODO-OWNER" on the public
    // site in the design's display type. Found in a browser pass.
    it('is refused', function () {
        $fair = Event::factory()->create([
            'name' => 'College Fair 2027 (date and price not yet confirmed — TODO-OWNER)',
            'is_published' => false,
        ]);

        livewire(EditEvent::class, ['event' => $fair])
            ->set('is_published', true)
            ->call('save')
            ->assertHasErrors(['is_published']);

        expect($fair->refresh()->is_published)->toBeFalse();
    });

    it('is allowed once the fair has a real name', function () {
        $fair = Event::factory()->create([
            'name' => 'College Fair 2027 (date and price not yet confirmed — TODO-OWNER)',
            'is_published' => false,
        ]);

        livewire(EditEvent::class, ['event' => $fair])
            ->set('name', 'College Fair 2027')
            ->set('is_published', true)
            ->call('save')
            ->assertHasNoErrors();

        expect($fair->refresh()->is_published)->toBeTrue();
    });

    it('still lets the placeholder be saved unpublished', function () {
        // The seeder writes exactly this row. Editing anything else about it
        // must not be blocked.
        $fair = Event::factory()->create([
            'name' => 'College Fair 2027 (date and price not yet confirmed — TODO-OWNER)',
            'is_published' => false,
        ]);

        livewire(EditEvent::class, ['event' => $fair])
            ->set('venue_name', 'Somewhere Else')
            ->call('save')
            ->assertHasNoErrors();

        expect($fair->refresh()->venue_name)->toBe('Somewhere Else');
    });
});
