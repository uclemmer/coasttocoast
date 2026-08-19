<?php

use App\Http\Controllers\EventInterestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| The two Filament panels register their own routes (/admin and /portal).
| Everything here is the public site. Phase 5 fills in the pages; the interest
| capture (card 3.4) is here already because the wizard and the roster both
| reference it.
|
*/

Route::get('/', fn () => view('welcome'))->name('home');

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
