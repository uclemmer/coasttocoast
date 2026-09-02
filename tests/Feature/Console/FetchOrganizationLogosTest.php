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

describe('choosing between the icons a site declares', function () {
    /**
     * A PNG whose IHDR says the given size. `getimagesizefromstring()` reads
     * the header and does not check the CRC, so this is enough to measure and
     * far clearer in a test than a fixture file.
     */
    function png(int $width, int $height): string
    {
        $ihdr = pack('NN', $width, $height)."\x08\x02\x00\x00\x00";

        return "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
    }

    /**
     * An ICO directory declaring the given square frames and no pixel data.
     */
    function ico(array $sizes): string
    {
        $data = pack('vvv', 0, 1, count($sizes));

        foreach ($sizes as $size) {
            $byte = $size === 256 ? 0 : $size;
            $data .= chr($byte).chr($byte)."\x00\x00".pack('vv', 1, 32).pack('VV', 0, 22);
        }

        return $data;
    }

    it('takes the largest touch icon, not the first one declared', function () {
        // The bug this fixes. A site supporting iOS properly declares one icon
        // per device generation and writes the smallest first, because the list
        // is historical — Auburn ships 57, 72, 76, 114, 120 and 144. Matching
        // one pattern and taking match [0] put a 57px image in a roster tile
        // with a 144 in the same <head>.
        rhodes();

        Http::fake([
            'www.rhodes.edu' => Http::response('<html><head>'
                .'<link rel="apple-touch-icon" sizes="57x57" href="/icon-57.png">'
                .'<link rel="apple-touch-icon" sizes="144x144" href="/icon-144.png">'
                .'</head></html>'),
            'www.rhodes.edu/icon-57.png' => Http::response(png(57, 57), 200, ['Content-Type' => 'image/png']),
            'www.rhodes.edu/icon-144.png' => Http::response(png(144, 144), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])
            ->expectsOutputToContain('144x144')
            ->assertSuccessful();

        expect(Storage::disk('public')->get('organization-logos/rhodes-college.png'))->toBe(png(144, 144));
    });

    it('reads an unsized touch icon as the modern 180, so it outranks a sized 57', function () {
        rhodes();

        Http::fake([
            'www.rhodes.edu' => Http::response('<html><head>'
                .'<link rel="apple-touch-icon" sizes="57x57" href="/icon-57.png">'
                .'<link rel="apple-touch-icon" href="/icon.png">'
                .'</head></html>'),
            'www.rhodes.edu/icon.png' => Http::response(png(180, 180), 200, ['Content-Type' => 'image/png']),
            'www.rhodes.edu/icon-57.png' => Http::response(png(57, 57), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

        expect(Storage::disk('public')->get('organization-logos/rhodes-college.png'))->toBe(png(180, 180));
    });

    it('refuses a sharing image that is a banner and keeps looking', function () {
        // Mississippi State's og:image is 2400x800 — the picture a link preview
        // wants, and not a logo. It used to win because it was matched second.
        rhodes();

        Http::fake([
            'www.rhodes.edu' => Http::response('<html><head>'
                .'<meta property="og:image" content="/banner.png">'
                .'<link rel="icon" sizes="128x128" href="/icon-128.png">'
                .'</head></html>'),
            'www.rhodes.edu/banner.png' => Http::response(png(2400, 800), 200, ['Content-Type' => 'image/png']),
            'www.rhodes.edu/icon-128.png' => Http::response(png(128, 128), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

        expect(Storage::disk('public')->get('organization-logos/rhodes-college.png'))->toBe(png(128, 128));
    });

    it('measures an ico by its largest frame, not its first', function () {
        // An ICO is a container: favicon.ico routinely holds 16 through 256 and
        // the browser picks. getimagesize() reports the FIRST entry, which is
        // conventionally the smallest, so measuring it the ordinary way calls a
        // 256x256 file 16x16. Bard and Trinity both read as unusable that way.
        rhodes();

        Http::fake([
            'www.rhodes.edu' => Http::response('<html><head><link rel="icon" href="/favicon.ico"></head></html>'),
            'www.rhodes.edu/favicon.ico' => Http::response(ico([16, 32, 256]), 200, ['Content-Type' => 'image/x-icon']),
        ]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])
            ->expectsOutputToContain('256x256')
            ->assertSuccessful();
    });

    it('understands rel="shortcut icon", which is two tokens meaning one thing', function () {
        rhodes();

        Http::fake([
            'www.rhodes.edu' => Http::response('<html><head><link rel="shortcut icon" href="/legacy.png"></head></html>'),
            'www.rhodes.edu/legacy.png' => Http::response(png(128, 128), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

        expect(Storage::disk('public')->get('organization-logos/rhodes-college.png'))->toBe(png(128, 128));
    });

    it('keeps a small icon when that is all there is, and says so', function () {
        // Rhodes really does declare nothing but a favicon. A small mark still
        // beats a letter, so it is stored — and counted, so the coordinator has
        // a worklist instead of a vague sense that some tiles look soft.
        rhodes();

        Http::fake([
            'www.rhodes.edu' => Http::response('<html><head><link rel="icon" href="/small.png"></head></html>'),
            'www.rhodes.edu/small.png' => Http::response(png(48, 48), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])
            ->expectsOutputToContain('48x48')
            ->assertSuccessful();

        expect(Organization::query()->matchingName('Rhodes College')->value('logo_path'))
            ->toBe('organization-logos/rhodes-college.png');
    });

    it('deletes the file it supersedes, so the fallback cannot resurrect it', function () {
        // These two features are each right alone and wrong together. The
        // stored name is <slug>.<extension>, so picking a different format
        // leaves the old file — and `recover()` prefers richer formats, so a
        // superseded .webp banner would outrank the .ico that replaced it and
        // come back on the next refusal. Rice was exactly this: a 1500x600
        // .webp rejected on shape, replaced by a 128x128 .ico.
        rhodes();
        Storage::disk('public')->put('organization-logos/rhodes-college.webp', png(1500, 600));

        Http::fake([
            'www.rhodes.edu' => Http::response('<html><head><link rel="icon" href="/favicon.ico"></head></html>'),
            'www.rhodes.edu/favicon.ico' => Http::response(ico([128]), 200, ['Content-Type' => 'image/x-icon']),
        ]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

        expect(Storage::disk('public')->exists('organization-logos/rhodes-college.webp'))->toBeFalse()
            ->and(Organization::query()->matchingName('Rhodes College')->value('logo_path'))
            ->toBe('organization-logos/rhodes-college.ico');
    });

    it('stops as soon as something is big enough, rather than fetching them all', function () {
        // Every candidate is a request to somebody else's server. A site that
        // declares its 180 first must cost exactly one.
        rhodes();

        Http::fake([
            'www.rhodes.edu' => Http::response('<html><head>'
                .'<link rel="apple-touch-icon" sizes="180x180" href="/big.png">'
                .'<link rel="apple-touch-icon" sizes="120x120" href="/medium.png">'
                .'</head></html>'),
            'www.rhodes.edu/big.png' => Http::response(png(180, 180), 200, ['Content-Type' => 'image/png']),
            'www.rhodes.edu/medium.png' => Http::response(png(120, 120), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->artisan('fair:fetch-organization-logos', ['--only' => 'Rhodes'])->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'medium.png'));
    });
});

it('ignores an organization the research never covered', function () {
    Organization::factory()->named('A College Nobody Researched')->create(['logo_path' => null]);

    Http::fake();

    $this->artisan('fair:fetch-organization-logos')->assertSuccessful();

    Http::assertNothingSent();
});
