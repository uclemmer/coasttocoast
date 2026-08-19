<?php

use App\Filament\Admin\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Admin\Resources\EventResource\Pages\EditEvent;
use App\Filament\Admin\Resources\EventResource\Pages\ListEvents;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    usingAdminPanel();
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

describe('listing', function () {
    it('shows every fair, published or not', function () {
        $published = Event::factory()->published()->create();
        $draft = Event::factory()->create();

        livewire(ListEvents::class)
            ->assertCanSeeTableRecords([$published, $draft]);
    });

    it('filters to published fairs', function () {
        $published = Event::factory()->published()->create();
        $draft = Event::factory()->create();

        livewire(ListEvents::class)
            ->filterTable('is_published', true)
            ->assertCanSeeTableRecords([$published])
            ->assertCanNotSeeTableRecords([$draft]);
    });
});

describe('creating', function () {
    it('creates a fair, converting the fee from dollars to cents', function () {
        livewire(CreateEvent::class)
            ->fillForm([
                'name' => 'College Fair 2028',
                'slug' => 'college-fair-2028',
                'venue_name' => 'Chattanooga Convention & Trade Center',
                'venue_address' => "1150 Carter Street\nChattanooga, TN 37402",
                'starts_at' => '2028-04-18 18:30',
                'ends_at' => '2028-04-18 20:00',
                'price_cents' => 215,
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Event::query()->where('slug', 'college-fair-2028')->first())
            ->price_cents->toBe(21500)
            ->is_published->toBeTrue();
    });

    it('rounds a fractional fee instead of truncating it', function () {
        // 215.10 * 100 is 21509.999999999996 in IEEE 754. Casting that to int
        // charges a school a cent less than it agreed to, silently, forever.
        livewire(CreateEvent::class)
            ->fillForm([
                'name' => 'Awkward Fee Fair',
                'slug' => 'awkward-fee-fair',
                'venue_name' => 'Somewhere',
                'venue_address' => 'Anywhere',
                'starts_at' => '2028-04-18 18:30',
                'ends_at' => '2028-04-18 20:00',
                'price_cents' => '215.10',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Event::query()->where('slug', 'awkward-fee-fair')->first()->price_cents)->toBe(21510);
    });

    it('suggests a slug from the name while creating', function () {
        livewire(CreateEvent::class)
            ->fillForm(['name' => 'College Fair 2029'])
            ->assertFormSet(['slug' => 'college-fair-2029']);
    });

    it('requires the fair to have a name, venue and dates', function () {
        livewire(CreateEvent::class)
            ->fillForm(['name' => null, 'slug' => null, 'venue_name' => null, 'venue_address' => null])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'required',
                'venue_name' => 'required',
                'venue_address' => 'required',
                'starts_at' => 'required',
                'ends_at' => 'required',
                'price_cents' => 'required',
            ]);
    });

    it('refuses a fair that ends before it starts', function () {
        livewire(CreateEvent::class)
            ->fillForm([
                'name' => 'Backwards Fair',
                'slug' => 'backwards-fair',
                'venue_name' => 'Somewhere',
                'venue_address' => 'Anywhere',
                'starts_at' => '2028-04-18 20:00',
                'ends_at' => '2028-04-18 18:30',
                'price_cents' => 215,
            ])
            ->call('create')
            ->assertHasFormErrors(['ends_at']);
    });

    it('refuses a registration window that closes before it opens', function () {
        livewire(CreateEvent::class)
            ->fillForm([
                'name' => 'Backwards Window Fair',
                'slug' => 'backwards-window-fair',
                'venue_name' => 'Somewhere',
                'venue_address' => 'Anywhere',
                'starts_at' => '2028-04-18 18:30',
                'ends_at' => '2028-04-18 20:00',
                'price_cents' => 215,
                'registration_opens_at' => '2028-03-01 09:00',
                'registration_closes_at' => '2028-01-01 09:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['registration_closes_at']);
    });

    it('refuses a negative fee', function () {
        livewire(CreateEvent::class)
            ->fillForm([
                'name' => 'Paying Fair',
                'slug' => 'paying-fair',
                'venue_name' => 'Somewhere',
                'venue_address' => 'Anywhere',
                'starts_at' => '2028-04-18 18:30',
                'ends_at' => '2028-04-18 20:00',
                'price_cents' => -5,
            ])
            ->call('create')
            ->assertHasFormErrors(['price_cents']);
    });

    it('refuses a duplicate slug', function () {
        Event::factory()->create(['slug' => 'taken']);

        livewire(CreateEvent::class)
            ->fillForm([
                'name' => 'Another Fair',
                'slug' => 'taken',
                'venue_name' => 'Somewhere',
                'venue_address' => 'Anywhere',
                'starts_at' => '2028-04-18 18:30',
                'ends_at' => '2028-04-18 20:00',
                'price_cents' => 215,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    });
});

describe('editing', function () {
    it('shows the stored fee in dollars and saves it back as cents', function () {
        $event = Event::factory()->priced(21500)->create();

        livewire(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertFormSet(['price_cents' => '215.00'])
            ->fillForm(['price_cents' => 225])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($event->refresh()->price_cents)->toBe(22500);
    });

    it('does not rewrite the slug when the name changes on an existing fair', function () {
        // Existing links are already out in the world. Renaming a fair must
        // not break them.
        $event = Event::factory()->create(['name' => 'College Fair 2027', 'slug' => 'college-fair-2027']);

        livewire(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['name' => 'Coast to Coast College Fair 2027'])
            ->assertFormSet(['slug' => 'college-fair-2027']);
    });

    it('publishes a fair', function () {
        $event = Event::factory()->create(['is_published' => false]);

        livewire(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['is_published' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($event->refresh()->is_published)->toBeTrue();
    });

    it('allows an open-ended registration window', function () {
        // Null on either side means no bound in that direction (R1.8).
        $event = Event::factory()->registrationOpen()->create();

        livewire(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['registration_opens_at' => null, 'registration_closes_at' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($event->refresh()->registration_opens_at)->toBeNull()
            ->and($event->isRegistrationOpen())->toBeTrue();
    });
});

describe('authorization', function () {
    it('keeps a user without the permission out of the resource entirely', function () {
        // Tested at the page, not by looking at navigation: a hidden menu item
        // is not authorization.
        $this->actingAs(User::factory()->rep()->create());

        livewire(ListEvents::class)->assertForbidden();
    });

    it('lets a coordinator in', function () {
        livewire(ListEvents::class)->assertSuccessful();
    });

    it('refuses to delete a fair that has registrations against it', function () {
        // The foreign keys cascade — deleting the fair would take real
        // financial history with it. Unpublish instead.
        $event = Event::factory()->create();
        Registration::factory()->forEvent($event)->create();

        expect($this->coordinator->can('delete', $event))->toBeFalse();

        $empty = Event::factory()->create();
        expect($this->coordinator->can('delete', $empty))->toBeTrue();
    });
});

describe('the table', function () {
    it('reports the three registration states the public page branches on', function () {
        Carbon::setTestNow('2027-02-01 12:00');

        $open = Event::factory()->registrationOpen()->create();
        $notYet = Event::factory()->registrationNotYetOpen()->create();
        $closed = Event::factory()->registrationClosed()->create();

        livewire(ListEvents::class)
            ->assertTableColumnStateSet('registration_status', 'Open', $open)
            ->assertTableColumnStateSet('registration_status', 'Not yet open', $notYet)
            ->assertTableColumnStateSet('registration_status', 'Closed', $closed);

        Carbon::setTestNow();
    });
});
