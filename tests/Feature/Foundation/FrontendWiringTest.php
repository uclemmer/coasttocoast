<?php

use Illuminate\Support\Facades\Blade;

/**
 * The public site's build pipeline (owner directive, 2026-08-19: frontend is
 * Blade + Livewire + Flowbite; Filament is the admin backend only).
 *
 * None of this asserts anything about how the site *looks* — the design
 * handoff has not landed and the layout is a deliberate placeholder. What it
 * pins is the plumbing, because every one of these failed silently rather than
 * loudly when it was wrong:
 *
 *  - Filament's assets were never published and every page rendered unstyled
 *    while returning 200 (doc 10, D-8-a);
 *  - Livewire 4's default `layouts::app` namespace does not resolve in this
 *    app, so a full-page component with no `#[Layout]` attribute dies at
 *    render time rather than at boot.
 */
describe('the Livewire page layout', function () {
    it('points at a view this application owns', function () {
        // Livewire 4 ships `layouts::app`, and `layouts` is not a registered
        // Blade view hint here — `component_namespaces` registers namespaces
        // for Livewire's component resolution, not for view lookup. A
        // full-page component would fail with "No hint path defined for
        // [layouts]" the first time somebody wrote one.
        $layout = config('livewire.component_layout');

        expect($layout)->toBe('components.layouts.app')
            ->and(view()->exists($layout))->toBeTrue();
    });

    it('is reachable as a Blade component too, so plain pages share one layout', function () {
        // The path is a component path as well as a view path, which is what
        // lets a static page use <x-layouts.app> and a Livewire page use the
        // same file without a second layout to keep in step.
        $rendered = Blade::render('<x-layouts.app title="A page">Body here</x-layouts.app>');

        expect($rendered)->toContain('Body here')
            // The site name is appended, so a browser tab reads
            // "About the fair — Coast to Coast College Fair".
            ->toContain('A page')
            ->toContain(config('app.name'));
    });

    it('loads both Vite entrypoints, which is where Flowbite lives', function () {
        // app.css carries `@plugin 'flowbite/plugin'`; app.js carries
        // Flowbite's behaviour. A layout that dropped either would leave the
        // site styled but inert, or unstyled and interactive.
        //
        // Asserted against the built manifest rather than the source paths:
        // once a build exists, `@vite` emits hashed URLs and the source path
        // never appears in the HTML.
        $manifest = json_decode(
            (string) file_get_contents(public_path('build/manifest.json')),
            true,
        );

        expect($manifest)->toHaveKeys(['resources/css/app.css', 'resources/js/app.js']);

        $rendered = Blade::render('<x-layouts.app>x</x-layouts.app>');

        expect($rendered)
            ->toContain($manifest['resources/css/app.css']['file'])
            ->toContain($manifest['resources/js/app.js']['file']);
    });
});

describe('the self-hosted fonts', function () {
    it('emits the face declarations the build produced', function () {
        // laravel-vite-plugin downloads Montserrat, Caveat and Source Sans 3 at
        // build time and writes public/build/fonts-manifest.json. Nothing
        // reaches the page unless the layout calls @fonts -- and the failure is
        // completely silent: every test passes, every page renders, just in the
        // fallback stack. The layout shipped without it once already.
        expect(file_exists(public_path('build/fonts-manifest.json')))->toBeTrue();

        $rendered = Blade::render('<x-layouts.app>x</x-layouts.app>');

        // @fonts inlines the @font-face rules rather than linking the CSS file,
        // and preloads the woff2 of each variant -- so assert on what actually
        // lands in the document, not on the manifest's filename.
        expect($rendered)->toContain('@font-face')
            ->toContain('font-family: "Montserrat"')
            ->toContain('font-family: "Caveat"')
            ->toContain('font-family: "Source Sans 3"')
            ->toContain('rel="preload" as="font"');
    });

    it('serves them from this origin, not from Google', function () {
        // Doc 10, D-8.1-a. The design handoff links Google Fonts; a public site
        // whose visitors are high schoolers and their parents should not
        // announce them to a third party before it paints.
        expect(Blade::render('<x-layouts.app>x</x-layouts.app>'))
            ->not->toContain('fonts.googleapis.com')
            ->not->toContain('fonts.gstatic.com');
    });
});

/*
 * These three used to assert that Flowbite WAS wired — in package.json, in the
 * CSS entrypoint, in the JS entrypoint. It left on 2026-08-21 under the
 * 2026-08-20 directive, so each is inverted rather than deleted: the wiring
 * they described is exactly the wiring that must not come back.
 */
describe('the Flowbite removal', function () {
    it('declares no flowbite dependency', function () {
        $package = json_decode((string) file_get_contents(base_path('package.json')), true);

        $offenders = array_values(array_filter(
            array_keys(($package['dependencies'] ?? []) + ($package['devDependencies'] ?? [])),
            fn (string $name) => str_contains($name, 'flowbite'),
        ));

        expect($offenders)->toBe([], 'package.json still requires: '.implode(', ', $offenders));
    });

    it('registers the sources Tailwind cannot auto-detect, and no longer flowbite', function () {
        // Tailwind v4 skips .gitignore'd directories, so these have to be
        // named. The package's Blade is the one whose absence is silent: its
        // components would render with a full class attribute and no styling.
        $css = (string) file_get_contents(resource_path('css/app.css'));

        /*
         * Comments have to come out before the negative half. That file carries
         * a note saying which @source line went and why, and a check that reads
         * the note as a leak is reading the wrong thing — it flagged exactly
         * that on the first run.
         */
        $rules = preg_replace('#/\*.*?\*/#s', '', $css);

        expect($css)->toContain('vendor/uclemmer/laravel-ui/resources/views')
            ->toContain('vendor/livewire/livewire/src');

        expect($rules)
            ->not->toContain('flowbite');
    });

    it('imports nothing in the JS entrypoint', function () {
        // The file is a comment now. Flowbite was its only import, and Alpine
        // must NOT replace it: Livewire bundles Alpine, so a direct import
        // would start a second one and double every handler.
        $js = (string) file_get_contents(resource_path('js/app.js'));

        // Strip the comment body before looking for statements, or the note
        // explaining all this reads as code.
        $statements = preg_replace('#/\\*.*?\\*/|//[^\n]*#s', '', $js);

        expect(trim((string) $statements))->toBe('');
        expect($js)->toContain('Alpine comes bundled with it');
    });

    it('emits livewireScripts, because most public pages render no component', function () {
        /*
         * The trap this whole change nearly fell into. Livewire injects its
         * assets only on a page that renders a component, and Alpine ships
         * inside that bundle. Most of this site is static Blade served by
         * SiteController, so without this line the FAQ accordion and the
         * hamburger render perfectly and do nothing at all — no console error,
         * and every markup assertion still passing.
         */
        $layout = (string) file_get_contents(resource_path('views/components/layouts/app.blade.php'));

        expect($layout)->toMatch('/^\s*@livewireScripts$/m');
    });

    it('actually puts alpine on a page that renders no livewire component', function () {
        // The assertion above reads the source; this one reads the response,
        // which is the thing that has to be true.
        $this->get('/faq')->assertOk()->assertSee('livewire.js', escape: false);
    });
});

describe('the Filament leftovers', function () {
    /*
     * These two assertions used to be their inverse: doc 10, D-8-a records the
     * whole application rendering as unstyled HTML while 609 tests passed,
     * because nothing had copied Filament's CSS and JS into public/. The fix
     * was a `filament:upgrade` hook on autoload dump, and a test pinning it.
     *
     * Filament then left the workspace entirely (docs/13, docs/14) and the
     * hook outlived it. `artisan filament:upgrade` now exits 1 with "There are
     * no commands defined in the filament namespace", which fails
     * `post-autoload-dump` -- so `composer install` was broken on any fresh
     * clone or deploy, while passing locally because vendor/ was already built.
     * The test defended the breakage rather than catching it, because the
     * stale published assets were still sitting in public/.
     *
     * Inverted rather than deleted. A test that says "this must be gone" is
     * worth more here than no test at all: the removal is the kind of thing a
     * copy-pasted composer.json or a re-run installer quietly undoes.
     */
    it('runs no Filament command on autoload dump', function () {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        expect($composer['scripts']['post-autoload-dump'] ?? [])
            ->not->toContain('@php artisan filament:upgrade')
            ->and($composer['require'] ?? [])->not->toHaveKey('filament/filament');
    });

    it('ships no published Filament assets', function () {
        expect(is_dir(public_path('css/filament')))->toBeFalse()
            ->and(is_dir(public_path('js/filament')))->toBeFalse()
            ->and(is_dir(public_path('fonts/filament')))->toBeFalse();
    });
});

describe('the maintenance page', function () {
    it('renders standalone, with no dependency on the Vite build', function () {
        // `artisan down --render=errors::503` freezes this view to a flat file
        // that is served for the whole outage -- which is precisely when
        // public/build is being swapped. An @vite call would bake in an asset
        // hash that no longer exists and the page would 404 its own stylesheet.
        // errors.503, not errors::503 -- the `errors` hint is registered
        // lazily by the exception handler, so the namespaced form does not
        // resolve from a cold container. `artisan down --render` registers it
        // itself, which the next test covers.
        $rendered = view('errors.503')->render();

        expect($rendered)->not->toContain('/build/assets/')
            ->toContain('Down for maintenance')
            ->toContain('/images/cityscape.jpg')
            ->toContain('mailto:'.config('fair.coordinator.email'));
    });

    it('freezes a flat page that survives its own deploy', function () {
        // The prerendered HTML is served for the whole outage -- including the
        // window where public/build is being replaced. If anything Vite-hashed
        // ever leaks into this view, the maintenance page 404s its own assets
        // at exactly the moment it is the only page running.
        $this->artisan('down', ['--render' => 'errors::503'])->assertSuccessful();

        try {
            $frozen = json_decode(
                (string) file_get_contents(storage_path('framework/down')),
                true,
            );

            expect($frozen['template'] ?? '')
                ->not->toContain('/build/')
                ->toContain('/images/cityscape.jpg')
                ->toContain('Down for maintenance');
        } finally {
            $this->artisan('up')->assertSuccessful();
        }
    });

    it('is the command the deploy runbook actually documents', function () {
        // doc 11's "every deploy" block. The view path and the flag live in two
        // files that nothing else connects; renaming errors/503.blade.php
        // without touching the runbook would leave a deploy rendering
        // Laravel's stock page and nobody would notice until an outage.
        $runbook = (string) file_get_contents(base_path('docs/11-deployment.md'));

        expect($runbook)->toContain('php artisan down --render="errors::503"')
            ->toContain('php artisan up')
            ->and(view()->exists('errors.503'))->toBeTrue();
    });

    it('serves the maintenance page while the site is down, and lets the site back up', function () {
        $this->artisan('down', ['--render' => 'errors::503'])->assertSuccessful();

        try {
            $this->get('/')->assertStatus(503)->assertSee('Down for maintenance');
        } finally {
            $this->artisan('up')->assertSuccessful();
        }

        $this->get('/')->assertOk();
    });
});

describe('the sidebar shells stack correctly', function () {
    /*
     * A browser pass on 2026-09-02 found the topbar brand invisible: the
     * sidebar is `fixed top-0 h-screen` with an opaque background at `z-40`,
     * the topbar was `z-30`, so the sidebar covered the leftmost 256px of the
     * bar and swallowed the brand whole. On /staff only the trailing "Staff"
     * poked out past the sidebar, which read like deliberate chrome for weeks.
     *
     * The sidebar's own `pt-16` is the proof of the intended order — it exists
     * to clear a bar it is drawn on top of.
     *
     * Asserted against the class strings, which is not the usual shape and is
     * the honest one: this is pure CSS stacking with no server-side behaviour
     * to exercise, and nothing in a Pest suite composites a page. The classes
     * are what regresses. Doc 10, D-10-d.
     */
    it('puts the topbar above the sidebar in every shell that has both', function (string $layout) {
        $markup = file_get_contents(resource_path("views/components/layouts/{$layout}.blade.php"));

        expect($markup)->toContain('<header class="fixed top-0 z-50 w-full')
            ->and($markup)->toContain('z-40 h-screen w-64')
            ->and($markup)->not->toContain('<header class="fixed top-0 z-30 w-full');
    })->with(['staff', 'portal']);
});
