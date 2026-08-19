<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeCheckoutService;
use App\Services\Sms\NullSms;
use App\Services\Sms\SmsService;
use App\Services\Sms\TwilioSms;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;
use Twilio\Rest\Client as TwilioClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bind the two vendor seams (doc 04).
     *
     * Both are keyed on configuration rather than on environment name. An app
     * with no Twilio credentials gets `NullSms` and keeps working — SMS is the
     * secondary channel and a missing variable must degrade one channel, not
     * break a registration. Tests inherit that automatically, so no test can
     * send a real message by forgetting to fake something.
     *
     * The Stripe binding is not conditional: there is no safe silent fallback
     * for taking money, so a missing secret must fail loudly at the point of
     * use. Payment tests bind their own fake `PaymentGateway`.
     */
    public function register(): void
    {
        $this->app->singleton(SmsService::class, function (): SmsService {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $from = config('services.twilio.from');

            if (blank($sid) || blank($token) || blank($from)) {
                return new NullSms;
            }

            return new TwilioSms(new TwilioClient($sid, $token), $from);
        });

        $this->app->singleton(PaymentGateway::class, fn (): PaymentGateway => new StripeCheckoutService(
            new StripeClient((string) config('services.stripe.secret')),
        ));
    }

    public function boot(): void
    {
        //
    }
}
