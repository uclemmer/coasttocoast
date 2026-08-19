<?php

use App\Filament\Site\Pages\EventPage;
use App\Filament\Site\Pages\Home;
use App\Filament\Site\Pages\LastYear;
use App\Filament\Site\Pages\Sponsors;
use App\Models\Event as Fair;
use App\Models\EventInterest;
use App\Models\FaqItem;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\SponsorStaff;
use Database\Seeders\ContentBlockSeeder;

beforeEach(fn () => usingSitePanel());

describe('every page is reachable without an account', function () {
    // The public site must never ask a visitor to become somebody. There is
    // no login on this panel and no Authenticate middleware.
    it('serves the site to a guest', function (string $path) {
        Fair::factory()->published()->create();

        $this->get($path)->assertOk();
    })->with(['/', '/about', '/representatives', '/last-year', '/sponsors', '/faq', '/contact']);

    it('does not redirect a guest to a login page', function () {
        $this->get('/')->assertOk()->assertDontSee('Sign in', escape: false);
    });
});

describe('the home page', function () {
    beforeEach(fn () => $this->seed(ContentBlockSeeder::class));

    it('leads with the next fair, its price and its times', function () {
        // The current site scatters all three (doc 00); a rep deciding whether
        // to come should not have to hunt.
        Fair::factory()->published()->priced(21500)->create([
            'name' => 'College Fair 2027',
            'venue_name' => 'Chattanooga Convention & Trade Center',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('College Fair 2027')
            ->assertSee('$215.00')
            ->assertSee('Chattanooga Convention & Trade Center');
    });

    it('renders the editable copy rather than hard-coded prose', function () {
        $this->get('/')->assertSee('More than 100 colleges', escape: false);
    });

    it('renders without a fair at all', function () {
        // A brand-new install, or the gap between one fair and the next.
        livewire(Home::class)->assertSuccessful();
    });
});

describe('content blocks', function () {
    it('renders a missing block as nothing at all', function () {
        // Not a placeholder and not an error: a half-seeded database should
        // leave a page one paragraph short, not print "content.missing" in
        // front of a hundred colleges.
        livewire(Sponsors::class)->assertSuccessful()->assertDontSee('sponsors.intro');
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

    it('respects the coordinator hiding a school', function () {
        Registration::factory()->hiddenFromRoster()->forEvent($this->fair)
            ->forOrganization($this->school)->create();

        $this->get('/representatives')->assertOk()->assertDontSee('Kenyon College');
    });

    it('drops a refunded school off the roster', function () {
        Registration::factory()->refunded()->forEvent($this->fair)
            ->forOrganization($this->school)->create();

        $this->get('/representatives')->assertOk()->assertDontSee('Kenyon College');
    });

    it('shows this year and last year separately', function () {
        // The bug doc 00 recorded: the Last Year page was showing the current
        // roster. Both pages read the same service, against different events.
        $lastYear = Fair::factory()->past(1)->create();
        $lastYearSchool = Organization::factory()->named('Berry College')->create();
        Registration::factory()->forEvent($lastYear)->forOrganization($lastYearSchool)->create();
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

        $this->get('/representatives')
            ->assertSee('Kenyon College')
            ->assertDontSee('Berry College');

        $this->get('/last-year')
            ->assertSee('Berry College')
            ->assertDontSee('Kenyon College');
    });

    it('says so plainly when there is no previous fair', function () {
        livewire(LastYear::class)->assertSuccessful();

        $this->get('/last-year')->assertOk()->assertSee('No previous fair on record yet');
    });

    it('falls back to an initial when a school has no logo', function () {
        // R1.3. Generated inline rather than fetched from an avatar service,
        // which would leak every visitor's request to a third party.
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

        $this->get('/representatives')->assertOk()->assertSee('data:image/svg+xml;base64', escape: false);
    });
});

describe('the sponsors page', function () {
    it('lists sponsors in billing order with their staff', function () {
        $second = Sponsor::factory()->ordered(1)->create(['name' => 'Girls Preparatory School']);
        $first = Sponsor::factory()->ordered(0)->create(['name' => 'Baylor School']);
        SponsorStaff::factory()->for($first)->create(['name' => 'Meg Conner', 'title' => 'Fair Coordinator']);

        $response = $this->get('/sponsors')->assertOk()->assertSee('Meg Conner');

        expect(strpos($response->getContent(), 'Baylor School'))
            ->toBeLessThan(strpos($response->getContent(), 'Girls Preparatory School'));
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

    it('renders markdown answers', function () {
        FaqItem::factory()->create(['question' => 'Anything?', 'answer' => 'Yes, **really**.']);

        $this->get('/faq')->assertSee('<strong>really</strong>', escape: false);
    });
});

describe('the contact page', function () {
    it('embeds laravel-core\'s form rather than a rebuilt one', function () {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('core-contact-form', escape: false)
            ->assertSee(route('core.contact.store'), escape: false);
    });

    it('states the privacy notice before the form', function () {
        $this->get('/contact')->assertOk()->assertSee('We do not share it with anyone');
    });

    it('shows the coordinator\'s address from config, the same source the emails use', function () {
        $this->get('/contact')
            ->assertOk()
            ->assertSee(config('fair.contact.name'))
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
        // The dead end on the current site, fixed: registration is shut for
        // most of the year and the page had nowhere to go.
        $fair = Fair::factory()->registrationClosed()->create();

        $this->get("/events/{$fair->slug}")
            ->assertOk()
            ->assertSee('Registration has closed')
            ->assertSee('Tell me when registration opens');
    });

    it('captures interest from the page itself', function () {
        $fair = Fair::factory()->registrationClosed()->create();

        livewire(EventPage::class, ['event' => $fair])
            ->fillForm(['email' => 'Dana@Kenyon.example', 'organization_name' => 'Kenyon College'])
            ->call('registerInterest')
            ->assertNotified();

        expect(EventInterest::query()->first())
            // Lowercased, so signing up twice does not mean being mailed twice.
            ->email->toBe('dana@kenyon.example')
            ->organization_name->toBe('Kenyon College');
    });

    it('drops a submission that filled in the honeypot', function () {
        $fair = Fair::factory()->registrationClosed()->create();

        livewire(EventPage::class, ['event' => $fair])
            ->fillForm(['email' => 'bot@example.com', 'website' => 'https://spam.example'])
            ->call('registerInterest');

        expect(EventInterest::query()->count())->toBe(0);
    });

    it('hides an unpublished fair as a 404, not a 403', function () {
        // A 403 would confirm the draft exists.
        $draft = Fair::factory()->create();

        $this->get("/events/{$draft->slug}")->assertNotFound();
    });
});
