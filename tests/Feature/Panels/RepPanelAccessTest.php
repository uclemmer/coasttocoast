<?php

use App\Models\User;

/*
 * Access to /portal.
 *
 * Was the rep Filament panel's access test until 2026-08-21; the portal is
 * Livewire now (docs/12) and the panel is gone, but every rule it pinned is
 * still a rule — who gets in, who is bounced to log in, and who is held at
 * email verification. The file is ported rather than deleted for exactly that
 * reason: the subject changed, the behaviour did not.
 *
 * What did change is where the URLs point. There is one login page now, at
 * /login, rather than one per panel.
 */

it('redirects guests to the login page', function () {
    $this->get('/portal')->assertRedirectContains('/login');
});

it('serves the login, registration and password reset pages', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/login',
    '/register',
    '/forgot-password',
]);

it('lets a verified rep into the portal', function () {
    $this->actingAs(rep())->get('/portal')->assertOk();
});

it('sends an unverified rep to the email verification prompt', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get('/portal')
        ->assertRedirectContains('/email/verify');
});

it('keeps a coordinator out of nothing — admin and the portal are independent', function () {
    $this->actingAs(coordinator())->get('/portal')->assertOk();
});

/*
 * Pins which layer enforces email verification, which is still worth pinning
 * even though the layer changed.
 *
 * Under Filament this mattered because the panel gate ran BEFORE the `verified`
 * middleware, so gating on hasVerifiedEmail() in canAccessPanel() sent
 * unverified reps to a dead end instead of to the page that fixes their
 * problem. The Livewire portal has no panel gate at all: `auth` and `verified`
 * are route middleware and run in that order, so an unverified rep reaches the
 * prompt rather than a 403. Same outcome, one fewer moving part.
 */
it('holds an unverified rep at the prompt rather than refusing them', function () {
    $unverified = User::factory()->unverified()->create();

    $this->actingAs($unverified)
        ->get('/portal')
        ->assertRedirectContains('/email/verify')
        ->assertStatus(302);

    // Not a 403: the point is that they are sent somewhere they can act.
    $this->actingAs($unverified)->get('/portal/grants')->assertRedirectContains('/email/verify');
});
