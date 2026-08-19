<?php

use App\Livewire\ContactForm;
use App\Livewire\EventCountdown;
use App\Livewire\EventInterest;
use App\Livewire\LastYearRoster;
use App\Livewire\RepresentativesRoster;
use App\Models\Event as Fair;
use App\Models\EventInterest as InterestRow;
use App\Models\FaqItem;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\SponsorStaff;
use Database\Seeders\ContentBlockSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use UClemmer\LaravelCore\Contact\ContactSubmission;

beforeEach(function () {
    // Both public forms throttle by IP, and the limiter outlives a single
    // test, so the fifth test in a file would otherwise inherit the fourth's
    // attempts.
    RateLimiter::clear('contact:127.0.0.1');
    RateLimiter::clear('event-interest:127.0.0.1');
});

describe('every page is reachable without an account', function () {
    it('serves the site to a guest', function (string $path) {
        Fair::factory()->published()->create();

        $this->get($path)->assertOk();
    })->with(['/', '/about', '/representatives', '/last-year', '/sponsors', '/faq', '/contact']);

    it('is Blade and Livewire, not a Filament panel', function () {
        // The owner directive of 2026-08-19. A Filament panel stamps `class="fi"`
        // on <html> and loads its own compiled CSS; the public site does
        // neither, and loads the Vite bundle instead.
        $html = $this->get('/')->assertOk()->getContent();

        expect($html)->not->toContain('class="fi"')
            ->not->toContain('/css/filament/')
            ->toContain('/build/assets/');
    });

    it('carries the site chrome on every page', function () {
        $this->get('/about')
            ->assertOk()
            ->assertSee('images/wordmark.jpg', escape: false)
            ->assertSee(route('site.representatives'), escape: false)
            ->assertSee('Powered by Uriah Clemmer');
    });
});

describe('the landing page', function () {
    beforeEach(fn () => $this->seed(ContentBlockSeeder::class));

    it('leads with the fair, the fee and the design\'s headline', function () {
        // Doc 00 lists pricing and deadlines being "scattered or missing" as a
        // weakness of the current site; a rep deciding whether to come should
        // not have to hunt.
        Fair::factory()->registrationOpen()->priced(21500)->create([
            'name' => 'College Fair 2027',
            'venue_name' => 'Chattanooga Convention & Trade Center',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Bring your college to Chattanooga')
            ->assertSee('$215.00')
            ->assertSee('Chattanooga Convention &amp; Trade Center', escape: false);
    });

    it('renders the editable copy rather than hard-coded prose', function () {
        // The hero copy is the design's and lives in the template; the
        // supporting prose is a content block the coordinator can edit.
        $this->get('/')->assertOk()->assertSee('more than one hundred colleges and universities');
    });

    it('swaps the registration copy with the fair\'s window', function () {
        $open = Fair::factory()->registrationOpen()->create();
        $open->update(['registration_closes_at' => Carbon::parse('2027-04-13 17:00')]);

        $this->get('/')->assertSee('is open now')->assertSee('Tuesday, April 13, 2027');

        $open->update(['is_published' => false]);
        Fair::factory()->registrationClosed()->create();

        $this->get('/')->assertSee('has closed');
    });

    it('renders with no published fair at all, and offers somewhere to go', function () {
        // A fresh install, or the gap between one fair and the next. It must
        // not build a URL for an event that does not exist.
        Fair::factory()->create(); // unpublished

        $this->get('/')
            ->assertOk()
            ->assertSee('has not been announced yet')
            ->assertSee(route('site.contact'), escape: false);
    });

    it('shows sponsors, holding a place for the logos that have not arrived', function () {
        Sponsor::factory()->ordered(0)->create(['name' => 'Baylor School']);

        $this->get('/')->assertOk()->assertSee('Sponsored by')->assertSee('Baylor School');
    });
});

describe('the countdown', function () {
    it('renders its first paint on the server, not after a round trip', function () {
        // Otherwise the hero is followed by four empty boxes until JavaScript
        // runs, and by nothing at all if it never does.
        $fair = Fair::factory()->published()->create([
            'starts_at' => Carbon::now()->addDays(10)->addHours(3),
        ]);

        livewire(EventCountdown::class, ['event' => $fair])
            ->assertSee('The fair opens in')
            ->assertSee('Days')
            ->assertSee('10');
    });

    it('does not poll the server', function () {
        // A one-second poll on a public marketing page is one request per
        // visitor per second. The ticking is an Alpine interval.
        $fair = Fair::factory()->published()->create(['starts_at' => Carbon::now()->addWeek()]);

        expect(livewire(EventCountdown::class, ['event' => $fair])->html())
            ->not->toContain('wire:poll')
            ->toContain('setInterval');
    });

    it('hides the numbers and changes the heading once the fair has happened', function () {
        $past = Fair::factory()->past(1)->create();

        livewire(EventCountdown::class, ['event' => $past])
            ->assertSee('has concluded')
            ->assertDontSee('Days');
    });
});

describe('the roster', function () {
    beforeEach(function () {
        $this->fair = Fair::factory()->published()->create();
        $this->school = Organization::factory()->named('Kenyon College')->create();
    });

    it('lists confirmed schools only', function () {
        // Test-inventory item 21. The roster is a promise the school will be
        // there, so an unpaid registration has no business on it.
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

        $pending = Organization::factory()->named('Pending College')->create();
        Registration::factory()->pendingCheck()->forEvent($this->fair)->forOrganization($pending)->create();

        $cancelled = Organization::factory()->named('Cancelled University')->create();
        Registration::factory()->cancelled()->forEvent($this->fair)->forOrganization($cancelled)->create();

        $this->get('/representatives')
            ->assertOk()
            ->assertSee('Kenyon College')
            ->assertDontSee('Pending College')
            ->assertDontSee('Cancelled University');
    });

    it('respects the coordinator hiding a school, and drops a refunded one', function () {
        Registration::factory()->hiddenFromRoster()->forEvent($this->fair)
            ->forOrganization($this->school)->create();

        $refunded = Organization::factory()->named('Refunded College')->create();
        Registration::factory()->refunded()->forEvent($this->fair)->forOrganization($refunded)->create();

        $this->get('/representatives')
            ->assertOk()
            ->assertDontSee('Kenyon College')
            ->assertDontSee('Refunded College');
    });

    it('shows this year and last year separately', function () {
        // The bug doc 00 recorded: the Last Year page was showing the current
        // roster. One component serves both, against different fairs.
        $lastYear = Fair::factory()->past(1)->create();
        $lastYearSchool = Organization::factory()->named('Berry College')->create();
        Registration::factory()->forEvent($lastYear)->forOrganization($lastYearSchool)->create();
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

        $this->get('/representatives')->assertSee('Kenyon College')->assertDontSee('Berry College');
        $this->get('/last-year')->assertSee('Berry College')->assertDontSee('Kenyon College');
    });

    it('renders its rows server-side, for search engines and for no-JavaScript', function () {
        // The roster IS the page. A list that only exists after a round trip
        // is invisible to both (doc 10, D-5.3-b).
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

        $this->get('/representatives')->assertSee('Kenyon College');
    });

    it('searches by institution name', function () {
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();
        $other = Organization::factory()->named('Rhodes College')->create();
        Registration::factory()->forEvent($this->fair)->forOrganization($other)->create();

        livewire(RepresentativesRoster::class)
            ->set('search', 'Kenyon')
            ->assertSee('Kenyon College')
            ->assertDontSee('Rhodes College');
    });

    it('says so plainly when there is no previous fair', function () {
        $this->get('/last-year')->assertOk()->assertSee('No previous fair on record yet');

        livewire(LastYearRoster::class)->assertSuccessful();
    });

    it('falls back to an initial when a school has no logo', function () {
        // R1.3. Generated inline rather than fetched from an avatar service,
        // which would leak every visitor's request to a third party.
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

        expect(livewire(RepresentativesRoster::class)->html())
            ->toMatch('/>\s*K\s*</');
    });
});

describe('the sponsors page', function () {
    it('lists sponsors in billing order with their staff', function () {
        $second = Sponsor::factory()->ordered(1)->create(['name' => 'Girls Preparatory School']);
        $first = Sponsor::factory()->ordered(0)->create(['name' => 'Baylor School']);
        SponsorStaff::factory()->for($first)->create(['name' => 'Meg Conner', 'title' => 'Fair Coordinator']);

        $body = $this->get('/sponsors')->assertOk()->assertSee('Meg Conner')->getContent();

        expect(strpos($body, 'Baylor School'))->toBeLessThan(strpos($body, 'Girls Preparatory School'));
    });
});

describe('the FAQ page', function () {
    it('shows published questions and hides the rest', function () {
        FaqItem::factory()->create(['question' => 'Where do we park?', 'answer' => 'Behind the centre.']);
        FaqItem::factory()->unpublished()->create(['question' => 'Secret question?']);

        $this->get('/faq')
            ->assertOk()
            ->assertSee('Where do we park?')
            ->assertDontSee('Secret question?');
    });

    it('renders markdown answers with real typography', function () {
        // Tailwind's preflight strips heading and list styling, so rendered
        // markdown needs explicit typography (doc 10, D-8-b).
        FaqItem::factory()->create(['question' => 'Anything?', 'answer' => "Yes, **really**.\n\n- one\n- two"]);

        $this->get('/faq')
            ->assertSee('<strong>really</strong>', escape: false)
            ->assertSee('list-disc', escape: false);
    });

    it('uses Flowbite\'s accordion rather than bespoke JavaScript', function () {
        FaqItem::factory()->create(['question' => 'Anything?', 'answer' => 'Yes.']);

        $this->get('/faq')->assertSee('data-accordion', escape: false);
    });
});

describe('the contact page', function () {
    it('renders our own form, not the package\'s unstyled one', function () {
        livewire(ContactForm::class)
            ->assertSuccessful()
            ->assertSet('consent', false)
            ->assertSee('Send message');

        $this->get('/contact')->assertOk()->assertDontSee('core-contact-form', escape: false);
    });

    it('stores a submission through laravel-core, which owns the receipt and the alert', function () {
        livewire(ContactForm::class)
            ->set('name', 'Dana Whitfield')
            ->set('email', 'dana@kenyon.example')
            ->set('institution', 'Kenyon College')
            ->set('message', 'Where do we unload?')
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        expect(ContactSubmission::query()->first())
            ->name->toBe('Dana Whitfield')
            ->email->toBe('dana@kenyon.example')
            // The package has no column for the institution, so it is folded
            // into the message rather than lost.
            ->message->toContain('Kenyon College')
            ->message->toContain('Where do we unload?');
    });

    it('validates the consent checkbox, so it means something', function () {
        livewire(ContactForm::class)
            ->set('name', 'Dana')
            ->set('email', 'dana@kenyon.example')
            ->set('message', 'Hello.')
            ->set('consent', false)
            ->call('submit')
            ->assertHasErrors(['consent']);

        expect(ContactSubmission::query()->count())->toBe(0);
    });

    it('drops a submission that filled in the honeypot', function () {
        livewire(ContactForm::class)
            ->set('name', 'Bot')
            ->set('email', 'bot@example.com')
            ->set('message', 'Buy things.')
            ->set('consent', true)
            ->set('website', 'https://spam.example')
            ->call('submit');

        expect(ContactSubmission::query()->count())->toBe(0);
    });

    it('rate-limits by IP, because a Livewire submit never touches core\'s throttled route', function () {
        foreach (range(1, 6) as $i) {
            livewire(ContactForm::class)
                ->set('name', "Person {$i}")
                ->set('email', "person{$i}@example.edu")
                ->set('message', 'Hello.')
                ->set('consent', true)
                ->call('submit');
        }

        expect(ContactSubmission::query()->count())->toBe(5);
    });

    it('shows the coordinator\'s address across lines, from the shared config', function () {
        $this->get('/contact')
            ->assertOk()
            ->assertSee(config('fair.contact.name'))
            ->assertSee(config('fair.contact.address_line1'))
            ->assertSee(config('fair.contact.email'));
    });
});

describe('the event page', function () {
    it('offers registration while the window is open', function () {
        $fair = Fair::factory()->registrationOpen()->priced(21500)->create();

        $this->get("/events/{$fair->slug}")
            ->assertOk()
            ->assertSee('Registration is open')
            ->assertSee('$215.00');
    });

    it('gives a date when registration has not opened yet', function () {
        // Distinct from closed: one is something to diarise, the other is a
        // reason to leave an email address.
        $fair = Fair::factory()->registrationNotYetOpen()->create();

        $this->get("/events/{$fair->slug}")
            ->assertOk()
            ->assertSee('Registration is not open yet')
            ->assertSee($fair->registration_opens_at->format('l, F j, Y'));
    });

    it('offers the interest form once registration has closed', function () {
        // The dead end on the current site, fixed.
        $fair = Fair::factory()->registrationClosed()->create();

        $this->get("/events/{$fair->slug}")
            ->assertOk()
            ->assertSee('Registration has closed')
            ->assertSee('Tell me when registration opens');
    });

    it('captures interest, lowercasing the address', function () {
        $fair = Fair::factory()->registrationClosed()->create();

        livewire(EventInterest::class, ['event' => $fair])
            ->set('email', 'Dana@Kenyon.example')
            ->set('organizationName', 'Kenyon College')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        expect(InterestRow::query()->first())
            // Signing up twice must not mean being mailed twice.
            ->email->toBe('dana@kenyon.example')
            ->organization_name->toBe('Kenyon College');
    });

    it('drops an interest submission that filled in the honeypot', function () {
        $fair = Fair::factory()->registrationClosed()->create();

        livewire(EventInterest::class, ['event' => $fair])
            ->set('email', 'bot@example.com')
            ->set('website', 'https://spam.example')
            ->call('submit');

        expect(InterestRow::query()->count())->toBe(0);
    });

    it('hides an unpublished fair as a 404, not a 403', function () {
        // A 403 would confirm the draft exists.
        $draft = Fair::factory()->create();

        $this->get("/events/{$draft->slug}")->assertNotFound();
    });
});
