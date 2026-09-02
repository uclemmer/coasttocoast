<?php

use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Every page in the application, loaded once, as the right person (card 7.2).
 *
 * Doc 05 asks for a browser smoke of both panels and the wizard. This is an
 * HTTP smoke instead — see doc 10, D-7.2-a. It catches what a browser pass
 * mostly catches in practice (a page that 500s, a resource whose query is
 * wrong, a Blade component that was renamed) without a driver, and it runs in
 * CI on a machine with no Chrome.
 *
 * Its real value is as a net for the next change: routes are discovered from
 * the router rather than listed, so a page added later is smoked without
 * anybody remembering to add it here.
 */
beforeEach(function () {
    $this->coordinator = coordinator();

    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->pastFair = Fair::factory()->past(1)->create();
    $this->organization = Organization::factory()->named('Kenyon College')->create();
    $this->rep = User::factory()->rep($this->organization)->create();

    $this->registration = Registration::factory()->forEvent($this->fair)
        ->forOrganization($this->organization)->create(['user_id' => $this->rep->id]);
    Registration::factory()->forEvent($this->pastFair)->forOrganization($this->organization)->create();
    $this->grant = Grant::factory()->for($this->fair)->for($this->organization)
        ->create(['requested_by' => $this->rep->id]);
    $this->message = Message::factory()->create(['event_id' => $this->fair->id]);
    Sponsor::factory()->create();
});

/**
 * GET routes for one panel, with their model parameters filled in.
 *
 * @return array<string, string>
 */
function panelUrls(string $prefix, array $bindings): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => in_array('GET', $route->methods(), true))
        ->map(fn ($route): string => $route->uri())
        ->filter(fn (string $uri): bool => $prefix === ''
            ? ! str_starts_with($uri, 'admin') && ! str_starts_with($uri, 'portal')
                && ! str_starts_with($uri, 'staff') && ! str_starts_with($uri, 'webhooks')
            : str_starts_with($uri, $prefix))
        // Livewire's own endpoints and Filament's asset routes are not pages.
        ->reject(fn (string $uri): bool => str_contains($uri, 'livewire')
            || str_contains($uri, '{fallbackPlaceholder}')
            || str_contains($uri, 'sanctum')
            || str_starts_with($uri, 'up')
            || str_starts_with($uri, 'storage')
            // The auth surface, whoever owns it. Behaviour for an
            // already-signed-in visitor (redirect, or refuse a reset link) is
            // covered by the panel access tests and tests/Feature/Auth.
            //
            // Two spellings for most of these: Filament's, and the ones this
            // app and laravel-core use now that auth is leaving the panel
            // (docs/12). `email/verify` in particular sits behind `auth`, so a
            // guest gets a redirect and this test would read it as a broken
            // public page.
            || str_contains($uri, 'login')
            || str_contains($uri, 'register')
            || str_contains($uri, 'password-reset')
            || str_contains($uri, 'forgot-password')
            || str_contains($uri, 'reset-password')
            || str_contains($uri, 'email-verification')
            || str_starts_with($uri, 'email/verify'))
        ->mapWithKeys(function (string $uri) use ($bindings): array {
            $filled = $uri;

            foreach ($bindings as $placeholder => $value) {
                $filled = str_replace($placeholder, (string) $value, $filled);
            }

            return [$uri => '/'.ltrim($filled, '/')];
        })
        // Anything still carrying a placeholder needs a binding this test does
        // not know about; skipped rather than requested as a literal "{id}".
        ->reject(fn (string $url): bool => str_contains($url, '{'))
        ->all();
}

it('serves every public page', function () {
    $urls = panelUrls('', ['{event:slug}' => $this->fair->slug]);

    expect($urls)->not->toBeEmpty();

    foreach ($urls as $uri => $url) {
        $this->get($url)->assertOk("Public page {$uri} did not load.");
    }
});

it('serves every admin page to a coordinator', function () {
    $this->actingAs($this->coordinator);

    $urls = panelUrls('admin', [
        '{record}' => $this->fair->getRouteKey(),
    ]);

    expect($urls)->not->toBeEmpty();

    foreach ($urls as $uri => $url) {
        // Every admin resource's {record} is an id, so one binding covers the
        // index and create pages; the record pages that would need a
        // per-resource id are covered by their own resource tests.
        $response = $this->get($url);

        expect($response->status())
            ->toBeIn([200, 404], "Admin page {$uri} returned {$response->status()}.");
    }
});

it('serves every portal page to an active representative', function () {
    $this->actingAs($this->rep);

    // The portal is Livewire now, so its parameter is {registration} rather
    // than Filament's {record}.
    $urls = panelUrls('portal', [
        '{record}' => $this->registration->getRouteKey(),
        '{registration}' => $this->registration->getRouteKey(),
    ]);

    expect($urls)->not->toBeEmpty();

    foreach ($urls as $uri => $url) {
        $response = $this->get($url);

        expect($response->status())
            ->toBeIn([200, 302, 404], "Portal page {$uri} returned {$response->status()}.");
    }
});

it('serves every staff page to a coordinator', function () {
    /*
     * The fair's own admin screens, rebuilt off Filament (docs/13). Swept the
     * same way as /admin and /portal, and added at the same time as the routes
     * — without the `staff` exclusion in panelUrls() above, these would have
     * been swept up as public pages instead and read as a broken site.
     */
    $this->actingAs($this->coordinator);

    $urls = panelUrls('staff', [
        '{sponsor}' => Sponsor::query()->value('id'),
    ]);

    expect($urls)->not->toBeEmpty();

    foreach ($urls as $uri => $url) {
        $response = $this->get($url);

        expect($response->status())
            ->toBeIn([200, 404], "Staff page {$uri} returned {$response->status()}.");
    }
});

it('keeps a representative out of every staff page', function () {
    // Each screen authorises itself on mount; this asserts none was missed.
    $this->actingAs($this->rep);

    $urls = panelUrls('staff', ['{sponsor}' => Sponsor::query()->value('id')]);

    expect($urls)->not->toBeEmpty();

    foreach ($urls as $uri => $url) {
        $this->get($url)->assertForbidden("Staff page {$uri} let a representative in.");
    }
});

it('serves the pages a coordinator actually works from, by name', function () {
    // The generic sweep tolerates a 404 for a mismatched binding; these are
    // the pages that must genuinely render, so they are asserted precisely.
    $this->actingAs($this->coordinator);

    /*
     * Moved from /admin to /staff on 2026-08-21 (docs/13). `/admin` itself
     * stays in the list because laravel-core's panel still serves users,
     * roles, content and settings there until step 4 of the workspace
     * Filament removal.
     */
    $pages = [
        '/admin',
        '/staff',
        '/staff/events',
        // Event's route key is its slug, on the staff side as on the public
        // site.
        '/staff/events/'.$this->fair->getRouteKey(),
        '/staff/organizations',
        '/staff/organizations/'.$this->organization->id,
        '/staff/registrations',
        '/staff/registrations/'.$this->registration->id,
        '/staff/grants',
        '/staff/grants/'.$this->grant->id,
        '/staff/sponsors',
        '/staff/faq',
        '/staff/interests',
        '/staff/messages',
        '/staff/messages/'.$this->message->id,
    ];

    foreach ($pages as $page) {
        $this->get($page)->assertOk("{$page} did not load.");
    }
});

it('serves the pages a representative actually works from, by name', function () {
    $this->actingAs($this->rep);

    $pages = [
        '/portal',
        '/portal/registrations',
        '/portal/registrations/create',
        '/portal/registrations/'.$this->registration->id,
        '/portal/grants',
        '/portal/organization-profile',
    ];

    foreach ($pages as $page) {
        $this->get($page)->assertOk("{$page} did not load.");
    }
});

it('serves the public site end to end', function () {
    foreach (['/', '/about', '/representatives', '/last-year', '/sponsors', '/faq', '/contact'] as $page) {
        $this->get($page)->assertOk("{$page} did not load.");
    }

    $this->get('/events/'.$this->fair->slug)->assertOk();
});
