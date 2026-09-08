<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| robots.txt
|--------------------------------------------------------------------------
|
| Served by uclemmer/laravel-core from config/core.php since 2026-09-07
| (docs/21). It replaced the stock public/robots.txt, which had said
| "User-agent: *" and a bare "Disallow:" -- permitting everything -- since the
| Laravel install, while the admin sat at /admin.
|
| This is a second line of defence and not the first one. A page is kept
| private by its own gate. What this keeps is the gated surfaces out of search
| indexes, so that the day a gate fails the damage is a leak rather than a
| search result.
|
*/

/**
 * Does one Disallow rule cover this path?
 *
 * Prefix match, plus `*` for any run of characters -- the wildcard every major
 * crawler understands and the original 1994 standard does not.
 */
function robotsCovers(string $rule, string $uri): bool
{
    if ($rule === '') {
        return false;
    }

    if (! str_contains($rule, '*')) {
        return str_starts_with($uri, $rule);
    }

    $pattern = implode('.*', array_map('preg_quote', explode('*', $rule)));

    return (bool) preg_match('#^'.$pattern.'#', $uri);
}

/**
 * The served document, as a crawler receives it.
 *
 * Fetched over HTTP rather than read off disk, which is the whole difference
 * from the version of this file `ckbs` carries: there the file is static, here
 * a route builds it from config, and reading config back would prove nothing
 * about what is actually served.
 *
 * @return list<string>
 */
function robotsDisallows(): array
{
    $body = test()->get('/robots.txt')->assertOk()->getContent();

    return collect(explode("\n", (string) $body))
        ->map(fn (string $line): string => trim($line))
        ->filter(fn (string $line): bool => str_starts_with(strtolower($line), 'disallow:'))
        ->map(fn (string $line): string => trim(substr($line, strlen('disallow:'))))
        ->values()
        ->all();
}

/**
 * Middleware arrive as class names, and matching them loosely is a trap this
 * test was written around: the substring "Authenticate" also matches
 * `RedirectIfAuthenticated`, which marks GUEST-only routes -- the opposite of
 * gated. That false positive put /register on the private list while the list
 * was being drawn up, and /register is a public page.
 */
function robotsIsGated(Illuminate\Routing\Route $route): bool
{
    $gates = [
        'Authenticate',
        'EnsureAuthenticated',
        'EnsureUserHasPermission',
        'EnsureEmailIsVerified',
        'RequireTwoFactor',
    ];

    foreach ($route->gatherMiddleware() as $middleware) {
        if (! is_string($middleware)) {
            continue;
        }

        $class = class_basename(explode(':', $middleware)[0]);

        if (in_array($class, $gates, true)) {
            return true;
        }
    }

    return false;
}

it('is served by the route, as plain text, with no session', function () {
    $response = $this->get('/robots.txt')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->toContain('User-agent: *')
        // No `web` group on the route, so a crawler is handed no cookie.
        ->and($response->headers->get('Set-Cookie'))->toBeNull();
});

it('no longer ships a static file that would shadow the route', function () {
    /*
     * Asserted as an ABSENCE, deliberately. Every web server answers a real
     * file under public/ before Laravel boots, so a file reappearing here
     * would silently take the whole feature back out -- the route would still
     * exist, still return 200 to this suite, and reach no crawler.
     *
     * The inverse of this assertion is the trap: `saltglass-chartworks` once
     * carried a test asserting Filament's published assets were PRESENT, which
     * passed happily while defending the very files that should have gone.
     */
    expect(file_exists(public_path('robots.txt')))->toBeFalse();
});

/**
 * The test that earns this file's keep. Every route behind a login is covered
 * by a rule here, so a new private area fails this rather than shipping
 * indexable.
 */
it('disallows every route that sits behind a login', function () {
    $disallows = robotsDisallows();

    $uncovered = collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->filter(fn ($route) => robotsIsGated($route))
        ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
        ->reject(function (string $uri) use ($disallows): bool {
            foreach ($disallows as $rule) {
                if (robotsCovers($rule, $uri)) {
                    return true;
                }
            }

            return false;
        })
        ->unique()
        ->values();

    expect($uncovered->all())->toBe([]);
});

it('disallows the routes whose URL is itself the credential', function () {
    /*
     * Worse than an indexed private page: there is no gate behind these to
     * refuse the visitor. `/reset-password/{token}` is public by necessity --
     * the token is the whole authorization.
     */
    expect(robotsDisallows())->toContain('/reset-password/');
});

it('disallows the uploads directory but not the pages that embed it', function () {
    $disallows = robotsDisallows();

    expect($disallows)->toContain('/storage/')
        ->and($disallows)->not->toContain('/');
});

/**
 * The other half, and the one a too-broad rule would break silently: the
 * fair's own pages have to stay findable. This is what a stray `Disallow: /`
 * would fail -- which is not hypothetical here, because core derives its rules
 * from config and this app's auth prefix is an EMPTY string. Core drops that
 * rather than emitting `/`; if it ever stopped, this test says so.
 */
it('keeps the public site crawlable', function () {
    $disallows = robotsDisallows();

    foreach (['/', '/about', '/events/fall-2026', '/faq', '/sponsors', '/representatives', '/contact'] as $uri) {
        $covered = collect($disallows)->contains(fn (string $rule): bool => robotsCovers($rule, $uri));

        expect($covered)->toBeFalse("robots.txt hides {$uri}, which is a public page");
    }
});

it('names the admin, which core derives rather than this app listing it', function () {
    // If `core.admin.path` moves, this follows it without anybody editing the
    // disallow list -- which is the reason the feature reads config at all.
    expect(robotsDisallows())->toContain('/'.config('core.admin.path'));
});
