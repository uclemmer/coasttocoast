<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * The authentication surface, now that laravel-core provides it.
 *
 * Filament owned every authenticated route in this application — there is no
 * Fortify or Breeze behind it — so turning core's routes on is the step that
 * makes retiring the rep panel possible at all (docs/12). These tests pin the
 * parts that are this app's configuration rather than the package's behaviour:
 * where the routes live, where they send people, and that the published views
 * still render after being restyled.
 *
 * The package's own suite covers whether logging in works. This covers whether
 * it works HERE.
 */

it('serves login and password reset at the site root, not behind a prefix', function () {
    get('/login')->assertOk()->assertSee('Log in');
    get('/forgot-password')->assertOk()->assertSee('Reset your password');
});

/*
 * The published views were restyled onto laravel-ui. If a component tag is
 * wrong or the theme import goes missing, this still passes — it can only see
 * that the page rendered. What it does catch is a view that throws.
 */
it('renders the restyled auth views through the app layout', function () {
    get('/login')
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        // The layout's chrome, so a swap back to the package's bare view shows up.
        ->assertSee(config('app.name'));
});

/*
 * Laravel's `auth` middleware redirects to a route literally named `login`,
 * and core names its route `core.login`. Without the explicit redirect in
 * bootstrap/app.php a guest hitting a protected page gets a
 * RouteNotFoundException — a 500 where a redirect belongs.
 *
 * Asserted against a route defined here rather than against `/portal`, which
 * is still the Filament rep panel and sends guests to its own login. When that
 * panel goes, `/portal` joins this behaviour and this probe can go with it.
 */
it('sends a guest to the login page rather than throwing', function () {
    Route::middleware(['web', 'auth'])->get('/__auth-probe', fn () => 'ok');

    get('/__auth-probe')->assertRedirect('/login');
});

/*
 * Documents the state DURING the migration, and fails when it ends: /portal is
 * still Filament's, with its own login. Deliberately a test rather than a
 * comment, so retiring the panel cannot quietly leave two login pages behind.
 */
it('still has the filament rep panel answering /portal, for now', function () {
    get('/portal')->assertRedirect('/portal/login');
});

it('sends a signed-in user away from the login page', function () {
    actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect('/portal');
});

it('logs a user out and returns them to the site', function () {
    actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    expect(auth()->check())->toBeFalse();
});

it('signs a user in and lands them in the portal', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);

    post('/login', ['email' => $user->email, 'password' => 'correct-horse'])
        ->assertRedirect('/portal');

    expect(auth()->id())->toBe($user->id);
});

it('rejects a wrong password without signing anyone in', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);

    post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors();

    expect(auth()->check())->toBeFalse();
});

/*
 * Core's registration stays off: signing up here claims or creates a school,
 * and that decides whether the account is active immediately (D9). The route
 * must not exist, or two registration paths would disagree about that.
 */
it('does not expose the package registration route', function () {
    expect(Route::has('core.register'))->toBeFalse();
});
