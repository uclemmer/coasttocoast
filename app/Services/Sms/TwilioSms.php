<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use Throwable;
use Twilio\Rest\Client;

/**
 * The real Twilio implementation.
 *
 * Catches everything on purpose. SMS is the secondary channel (decision D4):
 * a Twilio outage, a bad number or an unpaid balance must degrade to a logged
 * failure, never propagate into the caller and take an email or a registration
 * down with it (doc 04, Twilio rules).
 */
class TwilioSms implements SmsService
{
    public function __construct(
        protected Client $client,
        protected string $from,
    ) {}

    public function send(string $toE164, string $body): SmsResult
    {
        try {
            $message = $this->client->messages->create($toE164, [
                'from' => $this->from,
                'body' => $body,
            ]);

            return SmsResult::sent($message->sid);
        } catch (Throwable $e) {
            Log::error('SMS send failed', [
                'to' => $toE164,
                'error' => $e->getMessage(),
            ]);

            return SmsResult::failed($e->getMessage());
        }
    }
}
