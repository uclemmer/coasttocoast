<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
|
| Production needs `php artisan schedule:run` on a one-minute cron for any of
| this to happen — see docs/07-deployment.md (card 7.3).
|
*/

// Prunes rows past core.email_log.prune_after_days (400) and marks sends that
// never got a delivery confirmation as failed.
Schedule::command('core:prune-email-logs')->dailyAt('03:10');

// Contact submissions past core.contact.prune_after_days.
Schedule::command('core:prune-contact-submissions')->dailyAt('03:20');

/*
 * Scheduled campaigns (doc 07 §3).
 *
 * Every minute, because a coordinator who schedules a note for 9:00 means
 * 9:00. The command only dispatches; `SendEventBroadcast` stamps `sent_at`
 * before it fans out, so a double run cannot double-send.
 */
Schedule::command('fair:send-scheduled-campaigns')->everyMinute()->withoutOverlapping();

/*
 * Message recipient rows past the 24-month privacy promise (N3, card 7.1).
 */
Schedule::command('fair:prune-message-recipients')->dailyAt('03:30');

/*
 * The Stripe idempotency ledger. Processed rows only — an unprocessed one is a
 * delivery that failed halfway and is worth keeping (card 7.1).
 */
Schedule::command('fair:prune-stripe-events')->weeklyOn(1, '03:40');
