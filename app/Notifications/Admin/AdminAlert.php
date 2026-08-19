<?php

namespace App\Notifications\Admin;

use App\Channels\SmsChannel;
use App\Notifications\Concerns\RendersThemedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Everything the coordinator is told about (R3.7, card 6.2).
 *
 * One class with a headline, a details table and an optional link, rather than
 * five near-identical ones. Every alert has the same shape — something
 * happened, here are the facts, here is where to look — and five classes
 * differing by a subject line would be five places to keep in step.
 *
 * SMS is opt-in per alert: `$smsBody` null means email only, which is right
 * for a grant application and wrong for money arriving at 11pm.
 *
 * @param  array<string, string|null>  $rows
 */
class AdminAlert extends Notification implements ShouldQueue
{
    use Queueable, RendersThemedMail;

    /**
     * @param  array<string, string|null>  $rows
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $headline,
        public readonly array $rows = [],
        public readonly ?string $url = null,
        public readonly ?string $linkLabel = null,
        public readonly ?string $smsBody = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['mail'];

        if (filled($this->smsBody)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return $this->themed(
            view: 'emails.notifications.admin-alert',
            subject: $this->subject,
            data: [
                'headline' => $this->headline,
                'rows' => $this->rows,
                'url' => $this->url,
                'linkLabel' => $this->linkLabel,
            ],
        );
    }

    public function toSms(mixed $notifiable): ?string
    {
        // Kept short and link-free beyond the portal URL (doc 04): an alert
        // that needs scrolling on a phone at 11pm is not an alert.
        return $this->smsBody;
    }
}
