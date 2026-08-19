<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    |
    | Checkout is hosted, so the publishable key is only needed if a future
    | version renders anything client-side. The webhook secret is what makes
    | the webhook route trustworthy — without it, anyone who can reach the URL
    | can confirm a registration (doc 04).
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Twilio
    |--------------------------------------------------------------------------
    |
    | Leave any of these blank and the container binds `NullSms` instead of
    | `TwilioSms` (see AppServiceProvider). That is the intended local and test
    | configuration, not a degraded one.
    |
    */

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Postmark message streams
    |--------------------------------------------------------------------------
    |
    | Transactional mail (receipts, verification, check instructions) goes out
    | on `outbound`; campaigns go out on `broadcast`. Keeping them apart is
    | what stops a badly received bulk send from damaging the deliverability of
    | a receipt (doc 04).
    |
    | Note the token key: Laravel's postmark transport reads `services.postmark.token`.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
        'message_stream_id' => env('POSTMARK_MESSAGE_STREAM', 'outbound'),
        'broadcast_stream_id' => env('POSTMARK_BROADCAST_STREAM', 'broadcast'),
    ],

];
