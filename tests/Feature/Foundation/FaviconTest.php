<?php

/*
 * The site icon (docs/29). `public/favicon.ico` was a zero-byte file until
 * 2026-11-10, so every browser tab showed the generic page icon while the
 * layouts linked it as if it were real.
 */

it('ships a real icon in every format the layouts link', function () {
    $ico = (string) file_get_contents(public_path('favicon.ico'));

    expect(substr($ico, 0, 4))->toBe("\x00\x00\x01\x00")
        ->and(file_get_contents(public_path('favicon.svg')))->toContain('<svg')->toContain('#188042')
        ->and(substr((string) file_get_contents(public_path('apple-touch-icon.png')), 1, 3))->toBe('PNG');
});

it('links the icons from every public, portal and staff page', function (string $view) {
    expect(file_get_contents(resource_path("views/{$view}")))->toContain("@include('partials.favicons')");
})->with([
    'components/layouts/app.blade.php',
    'components/layouts/auth.blade.php',
    'components/layouts/portal.blade.php',
    'components/layouts/staff.blade.php',
    'components/errors/page.blade.php',
]);

it('renders the links on the home page', function () {
    $this->get('/')->assertOk()
        ->assertSee('favicon.svg', escape: false)
        ->assertSee('apple-touch-icon.png', escape: false);
});

it('gives core\'s /admin the same icon', function () {
    expect(config('core.admin.favicon'))->toBe('favicon.svg');
});
