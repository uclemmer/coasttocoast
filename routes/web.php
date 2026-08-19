<?php

use App\Http\Controllers\EventInterestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Three Filament panels register their own routes: /admin (laravel-core),
| /portal (RepPanelProvider) and the public site at the root
| (SitePanelProvider, card 5.1). Almost nothing is left here.
|
| The interest endpoint stays a plain POST route: it is the non-JavaScript
| path to the same capture the event page offers as a Livewire form, and a
| route with its own throttle is the only way to rate-limit it per IP.
|
*/

/*
 * "Tell me when registration opens."
 *
 * Rate-limited by IP because it is an unauthenticated write with no captcha:
 * the honeypot in the form request catches naive bots, and this catches the
 * ones that get past it. Five an hour is far more than any human needs and far
 * fewer than a script wants.
 */
Route::post('/events/{event}/interest', [EventInterestController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('events.interest');
