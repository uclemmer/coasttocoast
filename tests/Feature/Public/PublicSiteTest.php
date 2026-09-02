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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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
        $this->organization = Organization::factory()->named('Kenyon College')->create();
    });

    it('lists confirmed organizations only', function () {
        // Test-inventory item 21. The roster is a promise the organization will be
        // there, so an unpaid registration has no business on it.
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

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

    it('alphabetizes on the sort key, so a leading "The" does not misfile an organization', function () {
        // The roster is the list a visitor scans by eye, so the order is the
        // feature. Ordering by `name` put every "The University of …" under T.
        $birmingham = Organization::factory()->named('The University of Alabama at Birmingham')->create();
        $vanderbilt = Organization::factory()->named('Vanderbilt University')->create();

        foreach ([$vanderbilt, $birmingham, $this->organization] as $organization) {
            Registration::factory()->forEvent($this->fair)->forOrganization($organization)->create();
        }

        $this->get('/representatives')
            ->assertOk()
            ->assertSeeInOrder([
                'Kenyon College',
                'The University of Alabama at Birmingham',
                'Vanderbilt University',
            ]);
    });

    it('respects the coordinator hiding an organization, and drops a refunded one', function () {
        Registration::factory()->hiddenFromRoster()->forEvent($this->fair)
            ->forOrganization($this->organization)->create();

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
        $lastYearOrganization = Organization::factory()->named('Berry College')->create();
        Registration::factory()->forEvent($lastYear)->forOrganization($lastYearOrganization)->create();
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

        $this->get('/representatives')->assertSee('Kenyon College')->assertDontSee('Berry College');
        $this->get('/last-year')->assertSee('Berry College')->assertDontSee('Kenyon College');
    });

    it('renders its rows server-side, for search engines and for no-JavaScript', function () {
        // The roster IS the page. A list that only exists after a round trip
        // is invisible to both (doc 10, D-5.3-b).
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

        $this->get('/representatives')->assertSee('Kenyon College');
    });

    it('searches by institution name', function () {
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();
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

    it('falls back to an initial when an organization has no logo', function () {
        // R1.3. Generated inline rather than fetched from an avatar service,
        // which would leak every visitor's request to a third party.
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

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

    /*
     * The FAQ is `x-ui::accordion` from `uclemmer/laravel-ui` as of 2026-08-21.
     * It was Flowbite's before that, and this test asserted `data-accordion`.
     *
     * The component is the one the package struck from its roadmap for want of
     * a second application wanting one, and built when this FAQ and kerdoos's
     * became that second application. What is asserted here is the contract it
     * promises, not its class strings — those belong to the package and would
     * make this fail on a cosmetic upgrade.
     */
    it('renders the ui package accordion, one panel open at a time', function () {
        FaqItem::factory()->create(['question' => 'First?', 'answer' => 'Yes.']);
        FaqItem::factory()->create(['question' => 'Second?', 'answer' => 'Also yes.']);

        $html = $this->get('/faq')->assertOk()->getContent();

        expect($html)
            // One scope owns the open state; the items number themselves into it.
            ->toContain('x-data="{ open: null, count: 0 }"')
            // Bound to the live state, never written once as a literal.
            ->toContain('x-bind:aria-expanded="open === index"')
            ->toContain('role="region"')
            // h2, because the page title is the h1 and these are its sections.
            ->toContain('<h2>')
            ->not->toContain('data-accordion')
            ->not->toContain('aria-expanded="false"');

        // Both questions registered, and the first one starts open.
        expect(substr_count($html, 'index = count++'))->toBe(2);
        expect($html)->toContain('true && (open = index)');
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

    it('charges a honeypot trip against the rate limit', function () {
        // The limiter used to be incremented only on a successful send, so a
        // bot that tripped the honeypot was told "something went wrong" and
        // could retry for ever, booting the framework every time. A visitor
        // never fills that field, so charging for it costs them nothing.
        foreach (range(1, 5) as $i) {
            livewire(ContactForm::class)
                ->set('name', 'Bot')
                ->set('email', 'bot@example.com')
                ->set('message', 'Buy things.')
                ->set('consent', true)
                ->set('website', 'https://spam.example')
                ->call('submit');
        }

        expect(RateLimiter::tooManyAttempts('contact:127.0.0.1', ContactForm::MAX_ATTEMPTS_PER_HOUR))
            ->toBeTrue();

        // And the allowance is genuinely spent -- a real message from the same
        // address is now refused rather than merely counted.
        livewire(ContactForm::class)
            ->set('name', 'Dana Whitfield')
            ->set('email', 'dana@kenyon.example')
            ->set('message', 'Where do we unload?')
            ->set('consent', true)
            ->call('submit')
            ->assertHasErrors(['message']);

        expect(ContactSubmission::query()->count())->toBe(0);
    });

    it('does not charge a failed validation against the rate limit', function () {
        // Somebody mistyping their address should not burn an hour's
        // allowance. Validation runs before the limiter is touched.
        foreach (range(1, 6) as $i) {
            livewire(ContactForm::class)
                ->set('name', 'Dana')
                ->set('email', 'not-an-email')
                ->set('message', 'Hello.')
                ->set('consent', true)
                ->call('submit')
                ->assertHasErrors(['email']);
        }

        expect(RateLimiter::tooManyAttempts('contact:127.0.0.1', ContactForm::MAX_ATTEMPTS_PER_HOUR))
            ->toBeFalse();

        livewire(ContactForm::class)
            ->set('name', 'Dana Whitfield')
            ->set('email', 'dana@kenyon.example')
            ->set('message', 'Where do we unload?')
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors();
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

    it('charges a honeypot trip against the interest rate limit too', function () {
        // The two public forms share ThrottlesPublicSubmissions precisely so
        // this cannot be true of one and not the other.
        $fair = Fair::factory()->registrationClosed()->create();

        foreach (range(1, 5) as $i) {
            livewire(EventInterest::class, ['event' => $fair])
                ->set('email', 'bot@example.com')
                ->set('website', 'https://spam.example')
                ->call('submit');
        }

        expect(RateLimiter::tooManyAttempts('event-interest:127.0.0.1', EventInterest::MAX_ATTEMPTS_PER_HOUR))
            ->toBeTrue();
    });

    it('throttles the non-JavaScript path to the same limit', function () {
        // The plain POST writes to the same table. A limit that only guards the
        // Livewire path is not a limit -- and the two numbers live in a route
        // middleware string and a trait constant, which nothing else connects.
        $throttle = collect(Route::getRoutes()->getByName('events.interest')->gatherMiddleware())
            ->first(fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'throttle:'));

        expect($throttle)->toBe(
            'throttle:'.EventInterest::MAX_ATTEMPTS_PER_HOUR.','.(EventInterest::DECAY_SECONDS / 60)
        );
    });

    it('hides an unpublished fair as a 404, not a 403', function () {
        // A 403 would confirm the draft exists.
        $draft = Fair::factory()->create();

        $this->get("/events/{$draft->slug}")->assertNotFound();
    });
});

describe('the FAQ attachment download', function () {
    beforeEach(function () {
        Storage::fake(FaqItem::ATTACHMENT_DISK);

        $this->item = FaqItem::factory()->create([
            'question' => 'Can we get a W-9?',
            'is_published' => true,
            'attachment_path' => FaqItem::ATTACHMENT_DIRECTORY.'/stored-under-a-hash.pdf',
            'attachment_name' => 'coast-to-coast-w9.pdf',
        ]);

        Storage::disk(FaqItem::ATTACHMENT_DISK)
            ->put($this->item->attachment_path, '%PDF-1.4 pretend');
    });

    it('offers the download on the public page under the answer', function () {
        $this->get('/faq')
            ->assertOk()
            ->assertSee(route('site.faq.download', $this->item), escape: false)
            ->assertSee('coast-to-coast-w9.pdf');
    });

    it('serves the file under the name it was uploaded with', function () {
        // The stored name is a hash. Somebody filing a W-9 into their
        // accounts-payable system needs the real one.
        $this->get(route('site.faq.download', $this->item))
            ->assertOk()
            ->assertDownload('coast-to-coast-w9.pdf');
    });

    it('stops serving the file when the question is unpublished', function () {
        // The whole reason this is a route and not a public-disk URL. A
        // Storage::url() would keep serving for ever, and a signed W-9 carries
        // the fair EIN and an authorised signature (doc 10, D-9-c).
        $this->item->update(['is_published' => false]);

        $this->get(route('site.faq.download', $this->item))->assertNotFound();
        $this->get('/faq')->assertOk()->assertDontSee('coast-to-coast-w9.pdf');
    });

    it('404s when the row outlives the file', function () {
        // A database restore without the storage directory. Without the guard
        // the visitor gets a 500 on a link the page itself rendered.
        Storage::disk(FaqItem::ATTACHMENT_DISK)->delete($this->item->attachment_path);

        $this->get(route('site.faq.download', $this->item))->assertNotFound();
    });

    it('404s for a question with no attachment at all', function () {
        $plain = FaqItem::factory()->create(['is_published' => true]);

        $this->get(route('site.faq.download', $plain))->assertNotFound();
    });
});

describe('the venue map', function () {
    // doc 10, D-9-f. The fonts are self-hosted so a visitor is not announced to
    // Google before the page paints; shipping the Maps iframe eagerly would
    // have undone half of that on the same page.
    it('does not request Google on page load', function () {
        Fair::factory()->published()->create();

        $html = $this->get('/')->assertOk()->getContent();

        // The URL is in the markup -- Alpine needs it -- but not in a src that
        // the browser will fetch.
        expect($html)->not->toContain('<iframe src="https://www.google.com/maps/embed')
            ->toContain('x-if="loaded"');
    });

    it('offers a plain link that works without JavaScript', function () {
        // The button is x-cloak'd, so with no Alpine it never appears. The link
        // is outside the Alpine block for exactly that reason.
        Fair::factory()->published()->create();

        $this->get('/')->assertOk()->assertSee('maps/search/?api=1', escape: false);
    });
});
