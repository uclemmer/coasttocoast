<?php

namespace App\Listeners;

use App\Notifications\Admin\AdminAlert;
use Illuminate\Support\Facades\Notification;

/**
 * Where the coordinator's alerts go (R3.7, card 6.2).
 *
 * One place, because "who is the coordinator" is a configuration question and
 * five listeners answering it independently would drift the day the address
 * changes. `config('fair.alerts')`:
 *
 *  - `enabled` false silences everything — the switch for a bulk import, or
 *    for a coordinator on holiday;
 *  - `email` blank falls back to `mail.from.address`, so a host that forgets
 *    still finds the alerts somewhere obvious rather than losing them;
 *  - `phone` blank means email only. SMS is a bonus channel, never a
 *    requirement, and a missing number must not fail a queued job.
 */
class AdminAlerts
{
    public static function send(AdminAlert $alert): void
    {
        if (! config('fair.alerts.enabled', true)) {
            return;
        }

        $email = static::email();

        if (blank($email)) {
            return;
        }

        $routes = ['mail' => $email];

        if (filled($phone = config('fair.alerts.phone'))) {
            $routes['sms'] = $phone;
        }

        Notification::routes($routes)->notify($alert);
    }

    public static function email(): ?string
    {
        return config('fair.alerts.email') ?: config('mail.from.address');
    }
}
