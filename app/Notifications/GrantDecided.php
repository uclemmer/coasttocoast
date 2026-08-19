<?php

namespace App\Notifications;

use App\Enums\GrantStatus;
use App\Models\Grant;
use App\Notifications\Concerns\RendersThemedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The answer to a fee-assistance request (R4, card 3.5).
 *
 * One class for approved and denied rather than two, because the school is
 * waiting on a single question and the two answers share everything except a
 * paragraph. The copy mirrors the portal's status lines (doc 01 Appendix A) —
 * a school that reads the email and then opens the portal must not find the
 * two saying different things.
 *
 * A denial always carries the reason. "Denied", with nothing else, is how a
 * school is lost for good.
 */
class GrantDecided extends Notification implements ShouldQueue
{
    use Queueable, RendersThemedMail;

    public function __construct(
        public readonly Grant $grant,
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
        $this->grant->loadMissing(['event', 'organization']);

        $approved = $this->grant->status === GrantStatus::Approved;

        return $this->themed(
            view: 'emails.notifications.grant-decided',
            subject: $approved
                ? __('Your fee assistance request for :event was approved', [
                    'event' => (string) $this->grant->event?->name,
                ])
                : __('About your fee assistance request for :event', [
                    'event' => (string) $this->grant->event?->name,
                ]),
            data: ['grant' => $this->grant, 'approved' => $approved],
        );
    }
}
