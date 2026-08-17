<?php

use App\Models\User;

/*
 * Card 1.1 — /portal is the app's own panel. Any user with a verified email
 * gets in; membership rules (pending / active / retired) land with card 3.0.
 */

it('redirects guests to the portal login page', function () {
    $this->get('/portal')->assertRedirectContains('/portal/login');
});

it('serves the portal login, registration and password reset pages', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    '/portal/login',
    '/portal/register',
    '/portal/password-reset/request',
]);

it('lets a verified rep into the portal', function () {
    $this->actingAs(rep())->get('/portal')->assertOk();
});

it('sends an unverified rep to the email verification prompt', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get('/portal')
        ->assertRedirectContains('/portal/email-verification');
});

it('keeps a coordinator out of nothing — the two panels are independent', function () {
    $this->actingAs(coordinator())->get('/portal')->assertOk();
});
