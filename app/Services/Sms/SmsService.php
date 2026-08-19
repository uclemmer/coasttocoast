<?php

namespace App\Services\Sms;

/**
 * The one seam every text message in this app goes through.
 *
 * Nothing outside this namespace may touch the Twilio SDK (doc 04). Two
 * implementations exist: `TwilioSms` when credentials are configured, and
 * `NullSms` otherwise — which is what local development and the whole test
 * suite get, so no test can send a real message by accident.
 */
interface SmsService
{
    /**
     * Send one message.
     *
     * @param  string  $toE164  the destination in E.164 form, e.g. +15551234567
     */
    public function send(string $toE164, string $body): SmsResult;
}
