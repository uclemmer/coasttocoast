<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Enums\MessageChannel;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Notifications\CampaignMessage;
use App\Services\AudienceBuilder;
use App\Services\Audiences\RecipientDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a campaign (doc 07 §3).
 *
 * Three steps, in this order and for a reason:
 *
 *  1. **Resolve the audience now.** Not when the message was composed — a note
 *     scheduled to "lapsed schools" reaches whoever is lapsed at this moment
 *     (doc 07 §2 rule 6).
 *  2. **Freeze the result into `message_recipients`.** With snapshots of name,
 *     email and phone, so a later profile edit cannot rewrite the record of
 *     who was mailed, and with the organization id so results group by school.
 *  3. **Fan out one queued notification per recipient**, each carrying its own
 *     row's id in a header for delivery tracking.
 *
 * Guarded against double-sending: a message with `sent_at` already set does
 * nothing. A queue that retries this job after a timeout must not mail a
 * hundred schools twice.
 */
class SendEventBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}

    public function handle(AudienceBuilder $audiences): void
    {
        if ($this->message->isSent()) {
            return;
        }

        $recipients = $audiences->resolve(
            audience: $this->message->audience,
            reference: $this->message->referenceEvent(),
            filters: $this->message->audience_filters ?? [],
        );

        // Stamped before the fan-out, not after. If the process dies halfway
        // through a hundred notifications, a retry that re-resolved and
        // re-sent would be far worse than one that stops.
        $this->message->forceFill(['sent_at' => Carbon::now()])->save();

        $sendsSms = $this->message->usesChannel(MessageChannel::Sms) && filled($this->message->sms_body);

        foreach ($recipients as $recipient) {
            /** @var RecipientDto $recipient */
            $row = $this->freeze($recipient, $sendsSms);

            Notification::route('mail', $recipient->email)
                ->notify(new CampaignMessage($this->message, $row));

            if ($sendsSms && $recipient->canReceiveSms()) {
                Notification::route('sms', $recipient->phone)
                    ->notify(new CampaignMessage($this->message, $row));
            }
        }
    }

    /**
     * One frozen row per recipient.
     *
     * `sms_status` records `Skipped` rather than `Pending` for anyone who has
     * not opted in — the delivery table should say "we did not text this
     * person" rather than leaving a row that looks stuck forever.
     */
    protected function freeze(RecipientDto $recipient, bool $sendsSms): MessageRecipient
    {
        return $this->message->recipients()->create([
            ...$recipient->toRecipientRow(),
            'email_status' => DeliveryStatus::Pending,
            'sms_status' => $sendsSms && $recipient->canReceiveSms()
                ? DeliveryStatus::Pending
                : DeliveryStatus::Skipped,
        ]);
    }
}
