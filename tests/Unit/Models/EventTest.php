<?php

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('casts and slug', function () {
    it('casts dates, money and the publish flag', function () {
        $event = Event::factory()->create(['price_cents' => 21500, 'capacity' => 120]);

        expect($event->starts_at)->toBeInstanceOf(Carbon::class)
            ->and($event->price_cents)->toBeInt()->toBe(21500)
            ->and($event->capacity)->toBeInt()->toBe(120)
            ->and($event->is_published)->toBeFalse();
    });

    it('generates a slug from the name when none is given', function () {
        $event = Event::factory()->create(['name' => 'College Fair 2027', 'slug' => null]);

        expect($event->slug)->toBe('college-fair-2027');
    });

    it('leaves a supplied slug alone', function () {
        $event = Event::factory()->create(['name' => 'College Fair 2027', 'slug' => 'ctc-2027']);

        expect($event->slug)->toBe('ctc-2027');
    });
});

describe('isRegistrationOpen', function () {
    // The truth table from card 1.2. Every row is a state the public event page
    // and the wizard both branch on, so all of them are load-bearing.
    it('is closed while the event is unpublished, whatever the window says', function () {
        $event = Event::factory()->create([
            'is_published' => false,
            'registration_opens_at' => Carbon::now()->subWeek(),
            'registration_closes_at' => Carbon::now()->addWeek(),
        ]);

        expect($event->isRegistrationOpen())->toBeFalse();
    });

    it('is open inside the window', function () {
        expect(Event::factory()->registrationOpen()->create()->isRegistrationOpen())->toBeTrue();
    });

    it('is closed before the window opens', function () {
        $event = Event::factory()->registrationNotYetOpen()->create();

        expect($event->isRegistrationOpen())->toBeFalse()
            ->and($event->registrationNotYetOpen())->toBeTrue();
    });

    it('is closed after the window shuts', function () {
        $event = Event::factory()->registrationClosed()->create();

        expect($event->isRegistrationOpen())->toBeFalse()
            // Closed is not the same as not-yet-open: one shows the interest
            // form, the other a date notice.
            ->and($event->registrationNotYetOpen())->toBeFalse();
    });

    it('is permanently open on a published event with no window at all', function () {
        $event = Event::factory()->published()->create([
            'registration_opens_at' => null,
            'registration_closes_at' => null,
        ]);

        expect($event->isRegistrationOpen())->toBeTrue();
    });

    it('treats a null bound as no bound in that direction', function () {
        $openEnded = Event::factory()->published()->create([
            'registration_opens_at' => Carbon::now()->subWeek(),
            'registration_closes_at' => null,
        ]);
        $alwaysOpenUntil = Event::factory()->published()->create([
            'registration_opens_at' => null,
            'registration_closes_at' => Carbon::now()->addWeek(),
        ]);

        expect($openEnded->isRegistrationOpen())->toBeTrue()
            ->and($alwaysOpenUntil->isRegistrationOpen())->toBeTrue();
    });

    it('answers for a supplied moment rather than only for now', function () {
        $event = Event::factory()->published()->create([
            'registration_opens_at' => Carbon::parse('2027-01-01 09:00'),
            'registration_closes_at' => Carbon::parse('2027-03-01 17:00'),
        ]);

        expect($event->isRegistrationOpen(Carbon::parse('2027-02-01 12:00')))->toBeTrue()
            ->and($event->isRegistrationOpen(Carbon::parse('2026-12-31 12:00')))->toBeFalse()
            ->and($event->isRegistrationOpen(Carbon::parse('2027-03-02 12:00')))->toBeFalse();
    });
});

describe('isFull', function () {
    it('is never full without a capacity', function () {
        $event = Event::factory()->create(['capacity' => null]);
        Registration::factory()->count(3)->forEvent($event)->create();

        expect($event->isFull())->toBeFalse()
            ->and($event->remainingCapacity())->toBeNull();
    });

    it('counts awaiting-payment registrations against capacity, not only confirmed', function () {
        // Counting confirmed alone would let a run of mailed checks oversell
        // the room, and every oversell is a school turned away afterwards.
        $event = Event::factory()->withCapacity(2)->create();
        Registration::factory()->forEvent($event)->create();
        Registration::factory()->forEvent($event)->pendingCheck()->create();

        expect($event->occupiedSeats())->toBe(2)
            ->and($event->isFull())->toBeTrue()
            ->and($event->remainingCapacity())->toBe(0);
    });

    it('releases the seat a cancelled or refunded registration held', function () {
        $event = Event::factory()->withCapacity(2)->create();
        Registration::factory()->forEvent($event)->create();
        Registration::factory()->forEvent($event)->cancelled()->create();
        Registration::factory()->forEvent($event)->refunded()->create();

        expect($event->occupiedSeats())->toBe(1)
            ->and($event->isFull())->toBeFalse()
            ->and($event->remainingCapacity())->toBe(1);
    });
});

describe('priceFor', function () {
    // Test-inventory item 1: the most important truth table in the app. Every
    // price the app ever quotes, charges or snapshots comes through here.
    beforeEach(function () {
        $this->event = Event::factory()->priced(21500)->create();
        $this->organization = Organization::factory()->create();
    });

    it('charges list price with no organization at all', function () {
        expect($this->event->priceFor())->toBe(21500);
    });

    it('charges list price for an organization holding no grant', function () {
        expect($this->event->priceFor($this->organization))->toBe(21500);
    });

    it('charges nothing under a free grant', function () {
        Grant::factory()->free()->for($this->event)->for($this->organization)->create();

        expect($this->event->priceFor($this->organization))->toBe(0);
    });

    it('charges the custom price under a custom-price grant', function () {
        Grant::factory()->customPrice(5000)->for($this->event)->for($this->organization)->create();

        expect($this->event->priceFor($this->organization))->toBe(5000);
    });

    it('rounds a percentage discount down to the cent', function () {
        // 33% off $215.00 is $144.05, not $144.06 — the half cent goes to the
        // school. 21500 * 0.67 = 14405.0 exactly, so use a rate that does not
        // divide evenly to prove the floor.
        Grant::factory()->percentOff(35)->for($this->event)->for($this->organization)->create();

        expect($this->event->priceFor($this->organization))->toBe(13975);

        $awkward = Event::factory()->priced(21501)->create();
        Grant::factory()->percentOff(33)->for($awkward)->for($this->organization)->create();

        expect($awkward->priceFor($this->organization))->toBe(14405);
    });

    it('treats a 100 percent discount as free', function () {
        Grant::factory()->percentOff(100)->for($this->event)->for($this->organization)->create();

        expect($this->event->priceFor($this->organization))->toBe(0);
    });

    it('ignores pending, denied, revoked and withdrawn grants', function (string $state) {
        Grant::factory()->{$state}()->for($this->event)->for($this->organization)->create();

        expect($this->event->priceFor($this->organization))->toBe(21500);
    })->with([
        'pending' => 'pending',
        'denied' => 'denied',
        'revoked' => 'revoked',
        'withdrawn' => 'withdrawn',
    ]);

    it('ignores another school\'s grant', function () {
        $other = Organization::factory()->create();
        Grant::factory()->free()->for($this->event)->for($other)->create();

        expect($this->event->priceFor($this->organization))->toBe(21500);
    });

    it('ignores a grant approved for a different fair', function () {
        $otherEvent = Event::factory()->priced(21500)->create();
        Grant::factory()->free()->for($otherEvent)->for($this->organization)->create();

        expect($this->event->priceFor($this->organization))->toBe(21500);
    });

    it('falls back to list price when an approved grant records no benefit', function () {
        // A data fault, not a free ride: charge list and let the coordinator notice.
        Grant::factory()->free()->for($this->event)->for($this->organization)->create(['benefit_type' => null]);

        expect($this->event->priceFor($this->organization))->toBe(21500);
    });

    it('never returns a negative price from a custom-price grant', function () {
        Grant::factory()->customPrice(0)->for($this->event)->for($this->organization)->create();

        expect($this->event->priceFor($this->organization))->toBe(0);
    });
});

describe('scopes and the active event', function () {
    it('orders previous published events most recent first and excludes drafts and the future', function () {
        $twoYearsAgo = Event::factory()->past(2)->create();
        $lastYear = Event::factory()->past(1)->create();
        $unpublishedPast = Event::factory()->past(1)->create(['is_published' => false]);
        $upcoming = Event::factory()->published()->create();

        $previous = Event::query()->previousPublished()->get();

        expect($previous->pluck('id')->all())->toBe([$lastYear->id, $twoYearsAgo->id])
            ->and($previous->pluck('id'))->not->toContain($unpublishedPast->id)
            ->and($previous->pluck('id'))->not->toContain($upcoming->id);
    });

    it('treats the next unfinished published event as active', function () {
        Event::factory()->past(1)->create();
        $upcoming = Event::factory()->published()->create();
        Event::factory()->create(); // unpublished — must not win

        expect(Event::active()?->id)->toBe($upcoming->id);
    });

    it('falls back to the most recent past fair once this year is over', function () {
        $lastYear = Event::factory()->past(1)->create();

        expect(Event::active()?->id)->toBe($lastYear->id);
    });

    it('has no active event when nothing is published', function () {
        Event::factory()->count(2)->create();

        expect(Event::active())->toBeNull();
    });
});

describe('two fairs in one calendar year', function () {
    // The fair is annual today, but the owner has flagged that it may not stay
    // that way. Nothing in the app groups fairs by year -- every "which fair"
    // question is answered by ordering on starts_at -- so this is a property
    // worth pinning rather than a change worth making.

    beforeEach(function () {
        $this->spring = Event::factory()->published()->create([
            'name' => 'College Fair Spring 2030',
            'slug' => 'college-fair-spring-2030',
            'starts_at' => Carbon::parse('2030-04-23 18:30'),
            'ends_at' => Carbon::parse('2030-04-23 20:00'),
        ]);

        $this->fall = Event::factory()->published()->create([
            'name' => 'College Fair Fall 2030',
            'slug' => 'college-fair-fall-2030',
            'starts_at' => Carbon::parse('2030-10-15 18:30'),
            'ends_at' => Carbon::parse('2030-10-15 20:00'),
        ]);
    });

    it('treats the nearer one as active, then hands over to the later one', function () {
        Carbon::setTestNow('2030-01-10');
        expect(Event::active()?->id)->toBe($this->spring->id);

        // The evening after the spring fair. The site must move on to the fall
        // fair rather than to next April.
        Carbon::setTestNow('2030-04-24');
        expect(Event::active()?->id)->toBe($this->fall->id);

        Carbon::setTestNow(null);
    });

    it('makes the spring fair the previous one for the fall fair', function () {
        // "Previous" means the previous *fair*, not the previous year -- which
        // is what the Last Year roster and every cross-year campaign audience
        // read. With two fairs in a year the spring one is what a fall
        // attendee saw last, and a win-back list that skipped it would be
        // mailing people who came six months ago.
        expect(Event::query()->previousPublished($this->fall->starts_at)->first()?->id)
            ->toBe($this->spring->id);
    });

    it('keeps both fairs, because the slug is per fair and not per year', function () {
        // The seeder writes slugs out explicitly for exactly this reason. A
        // scheme that derived `college-fair-{year}` would have collided here,
        // and the unique index would have made it a hard failure at seed time.
        expect(Event::query()->whereYear('starts_at', 2030)->count())->toBe(2);
    });
});

describe('relationships', function () {
    it('resolves registrations, grants and interests', function () {
        $event = Event::factory()->create();
        Registration::factory()->count(2)->forEvent($event)->create();
        Grant::factory()->for($event)->create();
        $event->interests()->create(['email' => 'someone@example.edu']);

        expect($event->registrations()->count())->toBe(2)
            ->and($event->grants()->count())->toBe(1)
            ->and($event->interests()->count())->toBe(1);
    });

    it('counts only occupying registrations as seats', function () {
        $event = Event::factory()->create();
        Registration::factory()->forEvent($event)->create(['status' => RegistrationStatus::Confirmed]);
        Registration::factory()->forEvent($event)->create(['status' => RegistrationStatus::PendingPayment]);
        Registration::factory()->forEvent($event)->create(['status' => RegistrationStatus::Cancelled]);

        expect($event->occupiedSeats())->toBe(2);
    });
});
