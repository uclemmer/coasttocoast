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

describe('the Flowbite wiring', function () {
    it('declares flowbite as a runtime dependency, not a dev one', function () {
        // It ships to the browser, so a production `npm ci --omit=dev` must
        // still install it.
        $package = json_decode((string) file_get_contents(base_path('package.json')), true);

        expect($package['dependencies'] ?? [])->toHaveKey('flowbite');
    });

    it('registers the plugin and the sources Tailwind cannot auto-detect', function () {
        // Tailwind v4 skips .gitignore'd directories, so Flowbite's own JS —
        // which injects classes that appear in no template — would be purged
        // out of the build without an explicit @source.
        $css = (string) file_get_contents(resource_path('css/app.css'));

        expect($css)->toContain("@plugin 'flowbite/plugin'")
            ->toContain('node_modules/flowbite')
            ->toContain('vendor/livewire/livewire/src');
    });

    it('imports Flowbite in the JS entrypoint', function () {
        expect((string) file_get_contents(resource_path('js/app.js')))
            ->toContain("import 'flowbite'");
    });
});

describe('Filament assets', function () {
    it('publishes them, because Filament arrives transitively and its installer never ran', function () {
        // Doc 10, D-8-a: the whole application rendered as unstyled HTML while
        // passing 609 tests, because nothing had copied Filament's CSS and JS
        // into public/. `composer.json` now runs `filament:upgrade` on
        // autoload dump; this fails if that hook is ever dropped.
        expect(file_exists(public_path('css/filament/filament/app.css')))->toBeTrue()
            ->and(file_exists(public_path('js/filament/filament/app.js')))->toBeTrue();
    });

    it('keeps the hook that republishes them', function () {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        expect($composer['scripts']['post-autoload-dump'] ?? [])
            ->toContain('@php artisan filament:upgrade');
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
