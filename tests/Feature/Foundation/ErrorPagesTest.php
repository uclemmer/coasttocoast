<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;

/**
 * The four full-screen views the site falls back to (docs/16, from the design
 * handoff's "Error Pages.dc.html").
 *
 * Two things are worth pinning here and nothing else really is.
 *
 * THE SHELL MUST NOT REACH FOR THE BUILD. Every one of these renders at a
 * moment when the thing it would reach for may be the thing that broke: 503 is
 * frozen to a flat file while public/build is being replaced, and 500 renders
 * after an exception that may have come from a provider. A stray @vite call
 * would not fail a page-renders test — it would fail in production, once,
 * during an outage.
 *
 * THE FRAMEWORK MUST ACTUALLY PICK THEM UP. `resources/views/errors/404.blade.php`
 * is wired to the exception handler by filename alone. Nothing imports it,
 * nothing references it, and a typo in the name is a silent fall-back to
 * Laravel's stock grey page.
 */
$views = [
    '404' => ['Page not found', 'Well, this is awkward'],
    '403' => ['Access denied', 'Members only'],
    '500' => ['Something went wrong', 'That\'s on us'],
    '503' => ['Down for maintenance', 'We\'ll be right back'],
];

describe('the error views', function () use ($views) {
    it('renders each one standalone, with no dependency on the Vite build', function (string $code, string $heading, string $script) {
        $rendered = view('errors.'.$code)->render();

        expect($rendered)
            ->not->toContain('/build/assets/')
            ->not->toContain('@vite')
            ->toContain($heading)
            // e(): the copy carries apostrophes, and Blade escapes them.
            ->toContain(e($script))
            // The cityscape and the wordmark are static paths under public/,
            // for the same reason.
            ->toContain('/images/cityscape.jpg')
            ->toContain('/images/wordmark.jpg');
    })->with(collect($views)->map(fn (array $copy, string $code) => [$code, ...$copy])->values()->all());

    it('shows the status code on the three that have one, and not on the maintenance page', function () {
        foreach (['404', '403', '500'] as $code) {
            // aria-hidden: the numeral is decoration, and the sentence a
            // screen reader needs is the H1 under it.
            expect(view('errors.'.$code)->render())
                ->toContain('<div class="code" aria-hidden="true">'.$code.'</div>');
        }

        expect(view('errors.503')->render())->not->toContain('class="code"');
    });

    it('gives every page a way out, and the right second one', function () {
        // The design's button rules: home on all four, log in on 403 only,
        // the public contact address on 500.
        $contact = config('fair.contact.email');

        expect(view('errors.404')->render())
            ->toContain('href="'.url('/').'"')
            ->not->toContain('mailto:');

        expect(view('errors.403')->render())
            ->toContain('href="'.url('/').'"')
            ->toContain('href="'.url('/login').'"');

        expect(view('errors.500')->render())
            ->toContain('href="'.url('/').'"')
            ->toContain('mailto:'.$contact);
    });

    it('keeps them out of search results', function (string $code) {
        expect(view('errors.'.$code)->render())
            ->toContain('<meta name="robots" content="noindex">');
    })->with(array_map('strval', array_keys($views)));
});

describe('the exception handler', function () {
    it('renders our page for an unroutable URL', function () {
        $this->get('/no-such-page-exists-here')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Well, this is awkward');
    });

    it('renders our page when authorisation fails', function () {
        Route::get('/__test-forbidden', fn () => throw new AuthorizationException)
            ->middleware('web');

        $this->get('/__test-forbidden')
            ->assertForbidden()
            ->assertSee('Access denied');
    });

    it('renders our page for an unhandled exception once debug is off', function () {
        // Which is the only way it renders in production. With debug on the
        // framework serves its own stack trace and this view never runs, so a
        // broken 500.blade.php would sit undiscovered until the day it was
        // needed.
        config(['app.debug' => false]);

        Route::get('/__test-explodes', fn () => throw new RuntimeException('boom'))
            ->middleware('web');

        $this->get('/__test-explodes')
            ->assertStatus(500)
            ->assertSee('Something went wrong');
    });
});
