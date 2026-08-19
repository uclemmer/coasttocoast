<?php

namespace App\Channels;

use App\Services\Sms\SmsService;
use Illuminate\Notifications\Notification;

/**
 * The notification channel that texts (doc 04).
 *
 * A notification opts in by declaring `SmsChannel::class` in `via()` and
 * implementing `toSms($notifiable): ?string`. Returning null means "not this
 * one" — a per-notification opt-out that costs no branching in `via()`.
 *
 * Three things are refused silently, because each is a normal state rather
 * than an error: a notifiable with no `routeNotificationForSms()`, one who has
 * not opted in, and a message body that came back null. Throwing on any of
 * them would fail a queued job over somebody's preference.
 */
class SmsChannel
{
    public function __construct(
        protected SmsService $sms,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $to = $this->numberFor($notifiable, $notification);
        $body = $notification->toSms($notifiable);

        if (blank($to) || blank($body)) {
            return;
        }

        $this->sms->send($to, $body);
    }

    /**
     * Where to send, or null if nowhere.
     *
     * Laravel's own routing API, which dispatches to `routeNotificationForSms()`
     * when the notifiable defines one and reads `Notification::route('sms', …)`
     * when it does not. `User` returns a number only when `sms_opt_in` is true,
     * so consent is enforced at the source rather than remembered at every call
     * site (N3).
     */
    protected function numberFor(mixed $notifiable, ?Notification $notification = null): ?string
    {
        if (is_string($notifiable)) {
            return $notifiable;
        }

        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor('sms', $notification);

        return is_string($route) ? $route : null;
    }
}
