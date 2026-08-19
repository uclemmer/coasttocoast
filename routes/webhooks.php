<?php

use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
|
| Separate from web.php because these routes are exempt from CSRF and from the
| session middleware entirely: the caller is a server, not a browser, and its
| proof of identity is the signature rather than a token. Keeping them in their
| own file makes that exemption visible instead of buried in a middleware
| exclusion list.
|
*/

Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');
