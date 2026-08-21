<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * Email verification, app-owned (docs/12).
 *
 * The Filament rep panel provided this and neither laravel-core version does,
 * so it is three routes over Laravel's own machinery. What is worth pinning is
 * the security property — a verification link must only verify the person it
 * was issued to — and the two "already done" paths, which are reachable by a
 * back button and by clicking the emailed link twice.
 */

function verificationUrlFor(User $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);
}

it('shows the notice to an unverified user', function () {
    actingAs(User::factory()->unverified()->create())
        ->get('/email/verify')
        ->assertOk()
        ->assertSee('Confirm your email address');
});

it('sends a verified user onward rather than showing the notice', function () {
    actingAs(User::factory()->create())
        ->get('/email/verify')
        ->assertRedirect('/portal');
});

it('verifies from the emailed link', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();

    actingAs($user)
        ->get(verificationUrlFor($user))
        ->assertRedirect('/portal');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

/*
 * The property that matters. Without the id/hash check inside
 * EmailVerificationRequest, a valid link would verify whoever happened to be
 * signed in - which is how one person verifies another's address.
 */
it('refuses a link issued to somebody else', function () {
    $owner = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();

    actingAs($other)
        ->get(verificationUrlFor($owner))
        ->assertForbidden();

    expect($owner->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('refuses an unsigned link', function () {
    $user = User::factory()->unverified()->create();

    actingAs($user)
        ->get(route('verification.verify', [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]))
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('is a no-op when the link is clicked twice', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(verificationUrlFor($user))
        ->assertRedirect('/portal');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('resends the link on request', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    actingAs($user)->post('/email/verification-notification')->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not resend to somebody already verified', function () {
    Notification::fake();

    actingAs(User::factory()->create())
        ->post('/email/verification-notification')
        ->assertRedirect('/portal');

    Notification::assertNothingSent();
});

it('keeps the whole thing behind auth', function () {
    get('/email/verify')->assertRedirect('/login');
    post('/email/verification-notification')->assertRedirect('/login');
});
