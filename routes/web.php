<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\EventInterestController;
use App\Http\Controllers\SiteController;
use App\Livewire\Auth\Register;
use App\Livewire\LastYearRoster;
use App\Livewire\RepresentativesRoster;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The public site
|--------------------------------------------------------------------------
|
| Blade and Livewire (owner directive, 2026-08-19). Filament keeps the two
| authenticated panels — /admin (laravel-core) and /portal (RepPanelProvider) —
| and registers their routes itself.
|
| Route names are prefixed `site.` so the navigation can highlight the current
| page without matching on URLs.
|
*/

/*
 * Representative sign-up (D9), replacing the Filament rep panel's Register
 * page. Guest-only: a signed-in rep landing here could otherwise create a
 * second account and a second school membership.
 *
 * Outside the `site.` name group on purpose. This is an authentication screen,
 * not a site page, and the conventional route name is what packages and the
 * framework look for - laravel-core's own login view checks
 * Route::has('register') before offering a sign-up link.
 *
 * Login, logout and password reset are laravel-core's, registered by the
 * package from config/core.php `auth.routes`. Registration is ours because
 * signing up claims or creates a school, and which of those it is decides
 * whether the account is active immediately. See docs/12.
 */
Route::get('/register', Register::class)->middleware('guest')->name('register');

/*
 * Email verification, app-owned.
 *
 * The Filament rep panel supplied this and neither laravel-core version has
 * it, so these three routes are the whole of it - Laravel provides the
 * notification, the signed URL, the MustVerifyEmail contract and the
 * `verified` middleware. See App\Http\Controllers\Auth\EmailVerificationController.
 *
 * `signed` on the verify route is what makes the link unforgeable, and the
 * throttle on the resend is Laravel's own default for it.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::name('site.')->group(function (): void {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('/about', [SiteController::class, 'about'])->name('about');
    Route::get('/sponsors', [SiteController::class, 'sponsors'])->name('sponsors');
    Route::get('/faq', [SiteController::class, 'faq'])->name('faq');

    /*
     * The rosters are Livewire because they want search and pagination. One
     * component behind both, differing only in which fair it reads — the live
     * site's Last Year page was showing the *current* roster (doc 00), and
     * that is what happens when they are two pieces of code.
     */
    Route::get('/representatives', RepresentativesRoster::class)->name('representatives');
    Route::get('/last-year', LastYearRoster::class)->name('last-year');

    Route::get('/contact', [SiteController::class, 'contact'])->name('contact');

    /*
     * `{event:slug}` rather than an id: the slug is in every link the fair has
     * ever sent out.
     */
    Route::get('/events/{event:slug}', [SiteController::class, 'event'])->name('event');
});

/*
 * "Tell me when registration opens", as a plain POST.
 *
 * The event page offers the same capture as a Livewire form; this is the
 * non-JavaScript path, and the only place an IP throttle can hang. Both
 * lowercase the address before writing, so they cannot diverge in the way that
 * matters (doc 10, D-5.4-b).
 */
Route::post('/events/{event}/interest', [EventInterestController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('events.interest');
