<?php

namespace App\Services\Sms;

/**
 * What came back from a send attempt.
 *
 * Deliberately not an exception on failure. SMS is always the secondary
 * channel here (decision D4) and a Twilio outage must never take down the
 * email that matters — callers record the outcome on
 * `message_recipients.sms_status` and carry on (doc 04, Twilio rules).
 */
final readonly class SmsResult
{
    private function __construct(
        public bool $sent,
        public ?string $messageId = null,
        public ?string $error = null,
    ) {}

    public static function sent(?string $messageId = null): self
    {
        return new self(sent: true, messageId: $messageId);
    }

    public static function failed(string $error): self
    {
        return new self(sent: false, error: $error);
    }
}
