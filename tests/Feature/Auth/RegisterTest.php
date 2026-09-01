<?php

use App\Livewire\Auth\Register;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Representative sign-up (D9), rebuilt off the Filament rep panel (docs/12).
 *
 * The asymmetry between the two paths is the whole feature, so most of what is
 * pinned here is which path leaves somebody waiting and which does not — and
 * that the component delegates that decision to OrganizationService rather than
 * deciding it itself.
 */

it('serves the sign-up page to guests only', function () {
    get('/register')->assertOk()->assertSee('Create your account');

    actingAs(User::factory()->create())
        ->get('/register')
        ->assertRedirect('/portal');
});

/*
 * Claiming an existing organization leaves the rep PENDING. Anyone can say they
 * represent an organization, and behind that claim sit its registration history, its
 * grants and its place on the roster.
 */
it('leaves a rep claiming an existing organization waiting on a coordinator', function () {
    $organization = Organization::factory()->create(['name' => 'Vanderbilt University']);

    Livewire::test(Register::class)
        ->set('name', 'Dana Reed')
        ->set('email', 'dana@vanderbilt.test')
        ->set('password', 'correct-horse-battery')
        ->set('password_confirmation', 'correct-horse-battery')
        ->set('organization_choice', 'claim')
        ->set('organization_id', $organization->id)
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect('/portal');

    $user = User::where('email', 'dana@vanderbilt.test')->sole();

    expect($user->organization_id)->toBe($organization->id)
        ->and($user->membership_status->value)->toBe('pending');
});

/*
 * Adding an organization makes the founder ACTIVE immediately. There is nobody to
 * vouch for an organization only this person knows about, so waiting would mean
 * waiting on nothing.
 */
it('activates the founder of an organization nobody has registered yet', function () {
    Livewire::test(Register::class)
        ->set('name', 'Alex Fry')
        ->set('email', 'alex@neworganization.test')
        ->set('password', 'correct-horse-battery')
        ->set('password_confirmation', 'correct-horse-battery')
        ->set('organization_choice', 'create')
        ->set('organization_name', 'New Organization of Design')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect('/portal');

    $user = User::where('email', 'alex@neworganization.test')->sole();

    expect($user->membership_status->value)->toBe('active')
        ->and($user->organization->name)->toBe('New Organization of Design');
});

it('signs the new rep in', function () {
    Livewire::test(Register::class)
        ->set('name', 'Alex Fry')
        ->set('email', 'alex@neworganization.test')
        ->set('password', 'correct-horse-battery')
        ->set('password_confirmation', 'correct-horse-battery')
        ->set('organization_choice', 'create')
        ->set('organization_name', 'New Organization of Design')
        ->call('register');

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->email)->toBe('alex@neworganization.test');
});

/*
 * Fires Registered, which is what a verification notification listens for.
 * Email verification is app-owned and comes next; the event has to be right
 * before that can work at all.
 */
it('fires the Registered event', function () {
    Event::fake([Registered::class]);

    Livewire::test(Register::class)
        ->set('name', 'Alex Fry')
        ->set('email', 'alex@neworganization.test')
        ->set('password', 'correct-horse-battery')
        ->set('password_confirmation', 'correct-horse-battery')
        ->set('organization_choice', 'create')
        ->set('organization_name', 'New Organization of Design')
        ->call('register');

    Event::assertDispatched(Registered::class);
});

/* ── validation, which differs by path ─────────────────────────────────── */

it('requires an organization on the claim path and a name on the create path', function () {
    Livewire::test(Register::class)
        ->set('organization_choice', 'claim')
        ->call('register')
        ->assertHasErrors(['organization_id' => 'required']);

    Livewire::test(Register::class)
        ->set('organization_choice', 'create')
        ->call('register')
        ->assertHasErrors(['organization_name' => 'required']);
});

/*
 * Each path must not fail on the other's field - the reason validation is
 * built in rules() rather than as #[Validate] attributes.
 */
it('does not demand the other path\'s fields', function () {
    Livewire::test(Register::class)
        ->set('organization_choice', 'create')
        ->call('register')
        ->assertHasNoErrors('organization_id');

    $organization = Organization::factory()->create();

    Livewire::test(Register::class)
        ->set('organization_choice', 'claim')
        ->set('organization_id', $organization->id)
        ->call('register')
        ->assertHasNoErrors('organization_name');
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'taken@organization.test']);

    Livewire::test(Register::class)
        ->set('name', 'Alex Fry')
        ->set('email', 'taken@organization.test')
        ->set('password', 'correct-horse-battery')
        ->set('password_confirmation', 'correct-horse-battery')
        ->set('organization_choice', 'create')
        ->set('organization_name', 'New Organization')
        ->call('register')
        ->assertHasErrors('email');
});

/* ── the organization picker ─────────────────────────────────────────────────── */

it('does not search until there is something worth searching for', function () {
    Organization::factory()->create(['name' => 'Vanderbilt University']);

    Livewire::test(Register::class)
        ->set('organization_search', 'V')
        ->assertDontSee('Vanderbilt University')
        ->set('organization_search', 'Van')
        ->assertSee('Vanderbilt University');
});

it('holds the chosen organization and lets it be changed', function () {
    $organization = Organization::factory()->create(['name' => 'Vanderbilt University']);

    Livewire::test(Register::class)
        ->set('organization_search', 'Vand')
        ->call('choose', $organization->id)
        ->assertSet('organization_id', $organization->id)
        // The search box clears, so the list does not sit under the choice.
        ->assertSet('organization_search', '')
        ->call('clearChoice')
        ->assertSet('organization_id', null);
});

/*
 * Warns, never blocks (R2.7). A false positive that stops an organization registering
 * is worse than one a coordinator merges later.
 */
it('warns about a duplicate organization without preventing it', function () {
    Organization::factory()->create(['name' => 'Boston University']);

    Livewire::test(Register::class)
        ->set('organization_choice', 'create')
        ->set('organization_name', 'Boston University')
        ->assertSee('We already have')
        ->set('name', 'Alex Fry')
        ->set('email', 'alex@bu.test')
        ->set('password', 'correct-horse-battery')
        ->set('password_confirmation', 'correct-horse-battery')
        ->call('register')
        ->assertHasNoErrors();

    expect(Organization::where('name', 'Boston University')->count())->toBe(2);
});

/* ── abuse defences ────────────────────────────────────────────────────── */

/*
 * A Livewire submit never touches a throttled route, so the honeypot and the
 * rate limit live in the component - same reasoning as ContactForm.
 */
it('silently accepts a honeypot submission without creating anything', function () {
    Livewire::test(Register::class)
        ->set('website', 'http://spam.test')
        ->set('name', 'Bot')
        ->set('email', 'bot@spam.test')
        ->call('register')
        ->assertRedirect('/portal');

    expect(User::where('email', 'bot@spam.test')->exists())->toBeFalse();
});
