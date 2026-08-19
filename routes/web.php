<?php

use App\Http\Controllers\EventInterestController;
use App\Http\Controllers\SiteController;
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
