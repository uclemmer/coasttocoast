<?php

namespace App\Notifications;

use App\Models\Event;
use App\Notifications\Concerns\RendersThemedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Registration is open" — to the people who asked to be told (R2.7, card 6.5).
 *
 * Goes out on the broadcast stream. These recipients asked for exactly one
 * message and this is it; treating it as transactional would put a bulk send
 * on the stream that carries receipts (doc 04).
 */
class RegistrationOpenAnnouncement extends Notification implements ShouldQueue
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
        return $this->themed(
            view: 'emails.notifications.registration-open',
            subject: __('Registration for :event is now open', ['event' => $this->event->name]),
            data: ['event' => $this->event],
        );
    }

    protected function messageStream(): string
    {
        return (string) config('services.postmark.broadcast_stream_id', 'broadcast');
    }
}
