<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Enums\MessageChannel;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Notifications\Concerns\RendersThemedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * One coordinator's campaign, to one recipient (doc 07 §3).
 *
 * Carries `X-CTC-Recipient-Id` on the way out. laravel-core logs every send
 * with its headers, and `LinkEmailLogToRecipient` reads that header back to
 * join the log row to this recipient's row — which is what turns "we queued
 * it" into a real delivery status the coordinator can act on (doc 07 §4).
 *
 * Goes out on the **broadcast** stream, never the transactional one. Keeping
 * them apart is what stops a badly received bulk send from taking receipts
 * down with it (doc 04).
 */
class CampaignMessage extends Notification implements ShouldQueue
{
    use Queueable, RendersThemedMail;

    public function __construct(
        public readonly Message $message,
        public readonly MessageRecipient $recipient,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = [];

        if ($this->message->usesChannel(MessageChannel::Email) && filled($this->message->email_body)) {
            $channels[] = 'mail';
        }

        // The channel being selected is permission to try, not a promise. The
        // notifiable's own routing decides whether this recipient gets one,
        // and it says no unless they opted in.
        if ($this->message->usesChannel(MessageChannel::Sms) && filled($this->message->sms_body)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return $this->themed(
            view: 'emails.notifications.campaign',
            subject: $this->message->subject,
            data: [
                'subject' => $this->message->subject,
                'body' => $this->message->email_body,
            ],
        )->withSymfonyMessage(function (Email $email): void {
            $this->stampStream($email, $this->messageStream());

            // The correlation id. Named for this app rather than reusing
            // core's own header, which the package sets on the same message
            // for its own purpose.
            $email->getHeaders()->addTextHeader(MessageRecipient::HEADER, (string) $this->recipient->getKey());
        });
    }

    public function toSms(mixed $notifiable): ?string
    {
        return $this->message->sms_body;
    }

    protected function messageStream(): string
    {
        return (string) config('services.postmark.broadcast_stream_id', 'broadcast');
    }
}
