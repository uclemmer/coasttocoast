<?php

namespace App\Console\Commands;

use App\Listeners\AdminAlerts;
use App\Models\Event;
use App\Notifications\RegistrationReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Mail the registration report to everyone on `ADMIN_ALERT_EMAIL` (docs/29).
 *
 * Scheduled for the first of each month, for the fair the site is currently
 * about (`Event::active()`). `--event=<slug>` sends any other fair's list,
 * which is how last year's reps are pulled for an invitation:
 *
 *     php artisan fair:send-registration-report --event=college-fair-2026
 */
class SendRegistrationReport extends Command
{
    protected $signature = 'fair:send-registration-report {--event= : A fair slug; defaults to the active fair}';

    protected $description = 'Email the coordinator and client a CSV of every registration for a fair.';

    public function handle(): int
    {
        $slug = $this->option('event');

        $event = filled($slug)
            ? Event::query()->where('slug', $slug)->first()
            : Event::active();

        if (! $event instanceof Event) {
            $this->error(filled($slug) ? "No fair has the slug [{$slug}]." : 'There is no published fair to report on.');

            return self::FAILURE;
        }

        $emails = AdminAlerts::emails();

        if ($emails === []) {
            $this->error('No recipients: set ADMIN_ALERT_EMAIL (or MAIL_FROM_ADDRESS).');

            return self::FAILURE;
        }

        Notification::route('mail', $emails)->notify(new RegistrationReport($event));

        $this->info("Sent the {$event->name} report to ".implode(', ', $emails).'.');

        return self::SUCCESS;
    }
}
