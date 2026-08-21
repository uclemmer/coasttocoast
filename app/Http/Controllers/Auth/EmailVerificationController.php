<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Email verification, app-owned (docs/12).
 *
 * The Filament rep panel provided this with `->emailVerification()`, and
 * **neither laravel-core v0.2.0 nor v0.3.1 has verification routes** — checked
 * rather than assumed, which is what took a core upgrade off this migration's
 * critical path. Laravel supplies the notification, the `MustVerifyEmail`
 * contract, the signed URL and the `verified` middleware; what was missing is
 * three routes and somewhere to land. That is all this class is.
 *
 * It is deliberately not a Livewire component. There is no state and no
 * interaction here beyond one button that posts and redirects back.
 */
class EmailVerificationController extends Controller
{
    /**
     * The "check your email" page.
     *
     * Sends anyone already verified onward rather than showing them a notice
     * about something they have done — reachable by a back button, a bookmark,
     * or clicking the emailed link twice.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended('/portal')
            : view('auth.verify-email');
    }

    /**
     * The link in the email.
     *
     * `EmailVerificationRequest` does the work that matters: it checks the
     * signature, and that the id and hash in the URL match the signed-in user.
     * Without that last part a valid link would verify whoever happened to be
     * logged in, which is how one person verifies another's address.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/portal');
        }

        $request->fulfill();

        return redirect()->intended('/portal')
            ->with('status', __('Your email address is confirmed.'));
    }

    /**
     * Resend the link.
     *
     * Throttled by the route, not here — six a minute, which is Laravel's own
     * default for this and is generous enough for somebody genuinely waiting on
     * an email while being useless for anything else.
     */
    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/portal');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('We have sent you another link.'));
    }
}
