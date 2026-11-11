<?php

namespace App\Notifications;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Notifications\Concerns\RendersThemedMail;
use App\Services\RegistrationCsv;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Every registration for one fair, as a CSV, to the coordinator and the
 * client (docs/29).
 *
 * Sent monthly by `fair:send-registration-report`, which can also be run by
 * hand for any fair (e.g. last year's, to invite those reps back). The CSV is
 * built from `RegistrationCsv`, the same columns as the staff export.
 */
class RegistrationReport extends Notification implements ShouldQueue
{
    use Queueable, RendersThemedMail;

    public function __construct(
        public readonly Event $event,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        // Read at send time, not when queued, so the report is as of delivery.
        $registrations = $this->event->registrations()
            ->with(['organization', 'event', 'grant'])
            ->orderByDesc('created_at')
            ->get();

        $counts = collect(RegistrationStatus::cases())
            ->mapWithKeys(fn (RegistrationStatus $status): array => [
                $status->getLabel() => $registrations->where('status', $status)->count(),
            ])
            ->all();

        $collectedCents = (int) $registrations->where('status', RegistrationStatus::Confirmed)->sum('price_cents');

        return $this->themed(
            view: 'emails.notifications.registration-report',
            subject: __('Registration report: :event', ['event' => $this->event->name]),
            data: [
                'event' => $this->event,
                'total' => $registrations->count(),
                'counts' => $counts,
                'collectedCents' => $collectedCents,
            ],
        )->attachData(
            app(RegistrationCsv::class)->toString($registrations),
            'registrations-'.Str::slug($this->event->slug).'-'.now()->format('Y-m-d').'.csv',
            ['mime' => 'text/csv'],
        );
    }
}
