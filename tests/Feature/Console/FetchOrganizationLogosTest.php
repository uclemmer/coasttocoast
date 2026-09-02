<?php

use App\Models\Organization;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * `fair:fetch-organization-logos` (doc 19).
 *
 * Every test fakes the network. The command's whole job is to read a
 * third-party page and pull a file off it, so the one thing it must never do in
 * a suite is actually reach a university's web server.
 */
beforeEach(function () {
    Storage::fake('public');
});

/**
 * Rhodes College is in the researched data with `logo_source`
 * `https://www.rhodes.edu`, so it is the organization these fake responses
 * are wired to.
 */
function rhodes(array $attributes = []): Organization
{
    return Organization::factory()->named('Rhodes College')->create([
        'logo_path' => null,
        ...$attributes,
    ]);
}

it('prefers the touch icon over the sharing image, which is usually a photograph', function () {
    // Found by running it: Clemson's og:image is an aerial shot of the campus.
    // A roster tile wants the mark, and the touch icon is the one thing on a
    // page that is reliably the mark on its own.
    rhodes();

    Http::fake([
        'www.rhodes.edu' => Http::response('<html><head>'
            .'<meta property="og:image" content="https://www.rhodes.edu/img/campus-aerial.jpg">'
            .'<link rel="apple-touch-icon" href="/apple-touch-icon.png">'
            .'</head></html>'),
        '*' => Http::response('IMAGEDATA', 200, ['Content-Type' => 'image/png']),
    ]);

    $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes', '--dry-run' => true])
        ->expectsOutputToContain('https://www.rhodes.edu/apple-touch-icon.png')
        ->assertSuccessful();
});

it('resolves the image the institution nominates for sharing', function () {
    rhodes();

    Http::fake([
        'www.rhodes.edu' => Http::response('<html><head><meta property="og:image" content="https://www.rhodes.edu/img/mark.png"></head></html>'),
        'www.rhodes.edu/img/mark.png' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
    ]);

    $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

    expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))
        ->toBe('organization-logos/rhodes-college.png');

    Storage::disk('public')->assertExists('organization-logos/rhodes-college.png');
});

it('falls back through apple-touch-icon and then the favicon', function (string $head, string $expected) {
    rhodes();

    Http::fake([
        'www.rhodes.edu' => Http::response("<html><head>{$head}</head></html>"),
        '*' => Http::response('IMAGEDATA', 200, ['Content-Type' => 'image/png']),
    ]);

    $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes', '--dry-run' => true])
        ->expectsOutputToContain($expected)
        ->assertSuccessful();
})->with([
    'apple touch icon' => ['<link rel="apple-touch-icon" href="/apple-touch-icon.png">', 'https://www.rhodes.edu/apple-touch-icon.png'],
    'declared favicon' => ['<link rel="shortcut icon" href="/static/fav.png">', 'https://www.rhodes.edu/static/fav.png'],
    'nothing at all' => ['<title>Rhodes</title>', 'https://www.rhodes.edu/favicon.ico'],
]);

it('downloads nothing on a dry run', function () {
    rhodes();

    Http::fake([
        'www.rhodes.edu' => Http::response('<html><head><meta property="og:image" content="https://www.rhodes.edu/mark.png"></head></html>'),
    ]);

    $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes', '--dry-run' => true])->assertSuccessful();

    expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))->toBeNull();

    // The page is read to resolve the URL; the image itself is never requested.
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'mark.png'));
});

it('leaves a logo somebody already uploaded', function () {
    rhodes(['logo_path' => 'organization-logos/uploaded-by-hand.png']);

    Http::fake();

    $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

    expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))
        ->toBe('organization-logos/uploaded-by-hand.png');

    Http::assertNothingSent();
});

it('replaces one when told to', function () {
    rhodes(['logo_path' => 'organization-logos/stale.png']);

    Http::fake([
        'www.rhodes.edu' => Http::response('<html><head><meta property="og:image" content="https://www.rhodes.edu/new.svg"></head></html>'),
        'www.rhodes.edu/new.svg' => Http::response('<svg/>', 200, ['Content-Type' => 'image/svg+xml']),
    ]);

    $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes', '--force' => true])->assertSuccessful();

    expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))
        ->toBe('organization-logos/rhodes-college.svg');
});

it('refuses anything that is not an image', function () {
    rhodes();

    Http::fake([
        'www.rhodes.edu' => Http::response('<html><head><meta property="og:image" content="https://www.rhodes.edu/gone"></head></html>'),
        'www.rhodes.edu/gone' => Http::response('<html>404</html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

    expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))->toBeNull();
});

it('refuses anything over two megabytes', function () {
    rhodes();

    Http::fake([
        'www.rhodes.edu' => Http::response('<html><head><meta property="og:image" content="https://www.rhodes.edu/huge.png"></head></html>'),
        'www.rhodes.edu/huge.png' => Http::response(str_repeat('x', 2 * 1024 * 1024 + 1), 200, ['Content-Type' => 'image/png']),
    ]);

    $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

    expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))->toBeNull();
});

it('survives an institution whose site is down', function () {
    rhodes();

    Http::fake(['www.rhodes.edu' => Http::response('', 503)]);

    $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])
        ->expectsOutputToContain('unreachable')
        ->assertSuccessful();

    expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))->toBeNull();
});

describe('falling back to a copy already on disk', function () {
    /**
     * A file where a previous run would have left it. Filenames are the
     * organization's slug, which is what makes the fallback need no record of
     * what was fetched before.
     */
    function storedLogoForRhodes(string $extension = 'png'): string
    {
        $path = "organization-logos/rhodes-college.{$extension}";
        Storage::disk('public')->put($path, 'EARLIERDOWNLOAD');

        return $path;
    }

    it('keeps a good file when the site refuses the request', function () {
        // The case that prompted this: Rice and North Carolina Outward Bound
        // both served their logo one day and answered 403/406 the next. A
        // refusal is not the same as publishing no logo, and treating it as one
        // throws away a file already in storage.
        rhodes();
        $path = storedLogoForRhodes();

        Http::fake(['www.rhodes.edu' => Http::response('', 403)]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])
            ->expectsOutputToContain('kept the copy already on disk')
            ->assertSuccessful();

        expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))->toBe($path);
    });

    it('keeps a good file when the nominated image turns out not to be one', function () {
        rhodes();
        $path = storedLogoForRhodes();

        Http::fake([
            'www.rhodes.edu' => Http::response('<html><head><meta property="og:image" content="https://www.rhodes.edu/gone"></head></html>'),
            'www.rhodes.edu/gone' => Http::response('<html>404</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

        expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))->toBe($path);
    });

    it('prefers the richest format when several are stored', function () {
        // A favicon is the last resort here for the same reason it is last in
        // the resolution order.
        rhodes();
        Storage::disk('public')->put('organization-logos/rhodes-college.ico', 'FAVICON');
        Storage::disk('public')->put('organization-logos/rhodes-college.svg', 'VECTOR');

        Http::fake(['www.rhodes.edu' => Http::response('', 403)]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

        expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))
            ->toBe('organization-logos/rhodes-college.svg');
    });

    it('still reports unreachable when there is nothing to fall back to', function () {
        // The fallback must not paper over a genuine failure — an organization
        // with no file and no reachable site is still a gap to report.
        rhodes();

        Http::fake(['www.rhodes.edu' => Http::response('', 403)]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])
            ->expectsOutputToContain('unreachable')
            ->assertSuccessful();

        expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))->toBeNull();
    });

    it('writes nothing on a dry run', function () {
        rhodes();
        storedLogoForRhodes();

        Http::fake(['www.rhodes.edu' => Http::response('', 403)]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes', '--dry-run' => true])
            ->expectsOutputToContain('kept the copy already on disk')
            ->assertSuccessful();

        expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))->toBeNull();
    });
});

it('ignores an organization the research never covered', function () {
    Organization::factory()->named('A College Nobody Researched')->create(['logo_path' => null]);

    Http::fake();

    $this->artisan('fair:fetch-organization-logos')->assertSuccessful();

    Http::assertNothingSent();
});
