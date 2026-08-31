<?php

namespace App\Listeners;

use App\Models\MessageRecipient;
use Illuminate\Support\Facades\Log;
use Throwable;
use UClemmer\LaravelPostmaster\Events\MessageLogged;

/**
 * Ties a campaign's recipient row to the laravel-postmaster log row for that
 * send (doc 07 §4).
 *
 * The correlation rides out as an `X-CTC-Recipient-Id` header, which the
 * package captures along with everything else. That is what lets the message page show
 * a real per-recipient status — sent, failed, still sending — rather than the
 * optimistic "we queued it" that a local column can offer.
 *
 * **This listener must never break a send.** It runs after the mail has
 * already left, so a failure here would only turn a delivered email into a
 * failed job and a retry that sends it again. Everything is caught and logged.
 */
class LinkEmailLogToRecipient
{
    public function handle(MessageLogged $event): void
    {
        try {
            $recipientId = $this->recipientIdFrom($event->log->headers ?? []);

            if (blank($recipientId)) {
                // Every transactional email in the app lands here without the
                // header. Not an error — most mail is not a campaign.
                return;
            }

            MessageRecipient::query()
                ->whereKey($recipientId)
                ->update(['email_log_id' => $event->log->getKey()]);
        } catch (Throwable $e) {
            Log::warning('Could not link an email log to its campaign recipient.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pull our header out of the log row's stored headers.
     *
     * They are stored as raw `Name: value` strings, so this parses rather than
     * looks up. Case-insensitive, because mail transports are.
     *
     * @param  array<int|string, string>  $headers
     */
    protected function recipientIdFrom(array $headers): ?string
    {
        $needle = strtolower(MessageRecipient::HEADER).':';

        foreach ($headers as $key => $value) {
            // Two shapes in the wild: a list of "Name: value" lines, and a map
            // of name => value. Handle both rather than assume.
            if (is_string($key) && strtolower($key) === strtolower(MessageRecipient::HEADER)) {
                return trim($value);
            }

            if (is_string($value) && str_starts_with(strtolower($value), $needle)) {
                return trim(substr($value, strlen($needle)));
            }
        }

        return null;
    }
}
