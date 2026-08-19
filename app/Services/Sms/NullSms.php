<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * The SMS implementation that sends nothing.
 *
 * Bound whenever Twilio is not configured, which is local development and
 * every test run. It logs each message so a developer can see what *would*
 * have gone out, and keeps them in memory so a test can assert on them without
 * reaching for a log parser.
 *
 * This is a real binding rather than a test double: the point is that an app
 * with no Twilio credentials behaves correctly instead of throwing, so a
 * missing environment variable degrades one channel rather than breaking a
 * registration.
 */
class NullSms implements SmsService
{
    /**
     * Messages sent during this process, oldest first.
     *
     * @var array<int, array{to: string, body: string}>
     */
    protected array $messages = [];

    public function send(string $toE164, string $body): SmsResult
    {
        $this->messages[] = ['to' => $toE164, 'body' => $body];

        Log::info('SMS suppressed (NullSms)', ['to' => $toE164, 'body' => $body]);

        return SmsResult::sent('null-'.count($this->messages));
    }

    /**
     * @return array<int, array{to: string, body: string}>
     */
    public function sentMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return array<int, array{to: string, body: string}>
     */
    public function messagesTo(string $toE164): array
    {
        return array_values(array_filter(
            $this->messages,
            fn (array $message): bool => $message['to'] === $toE164,
        ));
    }

    public function flush(): void
    {
        $this->messages = [];
    }
}
