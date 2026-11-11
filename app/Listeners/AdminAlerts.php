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
 *  - `email` is one address or a comma-separated list (the client and the
 *    coordinator both get them; docs/29). Blank falls back to
 *    `mail.from.address`, so a host that forgets still finds the alerts
 *    somewhere obvious rather than losing them;
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

        $emails = static::emails();

        if ($emails === []) {
            return;
        }

        $routes = ['mail' => count($emails) === 1 ? $emails[0] : $emails];

        if (filled($phone = config('fair.alerts.phone'))) {
            $routes['sms'] = $phone;
        }

        Notification::routes($routes)->notify($alert);
    }

    /**
     * Everyone who gets the coordinator's mail: alerts, and the monthly
     * registration report (`fair:send-registration-report`).
     *
     * @return list<string>
     */
    public static function emails(): array
    {
        $configured = static::parseList(config('fair.alerts.email'));

        return $configured !== [] ? $configured : static::parseList(config('mail.from.address'));
    }

    /**
     * A comma-separated string, or an array, as a clean list of addresses.
     *
     * @return list<string>
     */
    public static function parseList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $items)));
    }
}
