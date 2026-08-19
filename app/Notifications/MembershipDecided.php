<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Notifications\Concerns\RendersThemedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The answer to "I represent this school" (D9, R4).
 *
 * A denial names the school explicitly and says what to do next, because the
 * realistic denial is a typo — somebody claimed the wrong one of two similarly
 * named institutions — and the person needs to know they can simply sign up
 * again for the right one.
 */
class MembershipDecided extends Notification implements ShouldQueue
{
    use Queueable, RendersThemedMail;

    public function __construct(
        public readonly bool $approved,
        public readonly ?Organization $organization = null,
        public readonly ?string $reason = null,
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
        return $this->themed(
            view: 'emails.notifications.membership-decided',
            subject: $this->approved
                ? __('You can now register :school for the fair', [
                    'school' => (string) $this->organization?->name,
                ])
                : __('About your request to represent :school', [
                    'school' => (string) $this->organization?->name,
                ]),
            data: [
                'approved' => $this->approved,
                'organization' => $this->organization,
                'reason' => $this->reason,
            ],
        );
    }
}
