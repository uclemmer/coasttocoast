<?php

use App\Models\Organization;
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
    // The original assertion here was `assertOk()`: a coordinator was not
    // *refused* the portal, which is the independence this test is about, and
    // that is still true. What changed on 2026-08-19 is that a coordinator with
    // no school is now redirected to /staff rather than shown a dashboard
    // telling them to contact themselves (doc 10, D-9-d).
    //
    // So the independence is asserted with a coordinator who has a school —
    // which is the case the two-surfaces claim was ever really about, and the
    // one the redirect deliberately leaves alone.
    // Assigned directly rather than through update(): User is `$guarded = ['*']`
    // and organization_id is not fillable, so mass assignment silently does
    // nothing here. That guard is deliberate — it is what stops a rep putting
    // themselves in someone else's school — and it cost this test one debugging
    // round to notice.
    $coordinator = coordinator();
    $coordinator->organization_id = Organization::factory()->create()->getKey();
    $coordinator->save();

    $this->actingAs($coordinator)->get('/portal')->assertOk();
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

/*
 * Where a coordinator lands (doc 10, D-9-d).
 *
 * Found in a browser pass, not by a test, and the reason is worth recording:
 * every other test here navigates straight to the screen it is testing, so
 * nothing ever exercised "sign in and see where you end up". core's
 * LoginController reads `core.auth.routes.redirect_to` as one string for
 * everybody, so the coordinator landed on a portal dashboard telling them to
 * contact the fair coordinator -- themselves -- with no link to /staff.
 */
describe('a coordinator sent to the portal', function () {
    it('is bounced to their own screens instead of a dead end', function () {
        $this->actingAs(coordinator());

        $this->get('/portal')->assertRedirect(route('staff.dashboard'));
    });

    it('is bounced from every portal route, not only the dashboard', function (string $path) {
        // The middleware guards the group. A bookmark to a deeper portal page
        // would otherwise still strand them.
        $this->actingAs(coordinator());

        $this->get($path)->assertRedirect(route('staff.dashboard'));
    })->with(['/portal/registrations', '/portal/grants', '/portal/profile']);

    it('follows the redirect to a staff dashboard that actually renders', function () {
        // Registration is not rendering: asserting the redirect alone would
        // pass against a 500 at the other end.
        $this->actingAs(coordinator());

        $this->get('/portal')->assertRedirect(route('staff.dashboard'));
        $this->get(route('staff.dashboard'))->assertOk()->assertSee('Overview');
    });
});

describe('who the bounce leaves alone', function () {
    it('leaves a rep with no school on the portal, where the message is true', function () {
        // For them "contact the fair coordinator to be added" is correct and
        // actionable, and /staff would 403.
        $rep = User::factory()->rep()->create(['organization_id' => null]);

        $this->actingAs($rep)->get('/portal')
            ->assertOk()
            ->assertSee('not attached to a school');
    });

    it('leaves an ordinary rep alone', function () {
        $this->actingAs(User::factory()->rep()->create())->get('/portal')->assertOk();
    });
});
