<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeCheckoutService;
use App\Services\Sms\NullSms;
use App\Services\Sms\SmsService;
use App\Services\Sms\TwilioSms;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Blade;
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

    /**
     * Register the email theme's components under an `emails::` prefix.
     *
     * They live in `resources/views/emails/` rather than
     * `resources/views/components/` because doc 07 §1 names that path and
     * because keeping the whole theme in one directory is worth more than
     * Laravel's default component convention.
     *
     * The components sit flat beside the layout — `emails/panel.blade.php`,
     * not `emails/components/panel.blade.php`. A prefixed anonymous path
     * resolves `<x-emails::layout>` but NOT a nested `<x-emails::components.panel>`:
     * Blade leaves the latter uncompiled and prints the raw tag into the
     * email, which is exactly as visible as it sounds and exactly as easy to
     * miss until somebody reads a receipt.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/emails'), 'emails');

        $this->trustConfiguredProxies();
    }

    /**
     * Who may tell this application the visitor's IP address.
     *
     * Both public forms throttle on `request()->ip()`, and so does the
     * `throttle:5,60` on the plain interest POST. Behind a load balancer or a
     * CDN that address is the *proxy's* until the proxy is trusted — so every
     * visitor shares one throttle bucket and the fifth message of the hour from
     * anybody locks out everybody.
     *
     * **Here rather than in `bootstrap/app.php`, deliberately.** That file's
     * `withMiddleware` closure runs while the kernel is being resolved, before
     * the config repository is bound: `config()` there is a fatal, and `env()`
     * there silently returns null the moment `config:cache` runs, because that
     * stops `.env` being loaded at all — so it would work in development and
     * quietly do nothing in the one environment it exists for. Both were tried;
     * see docs/10, D-9-b. `TrustProxies::at()` is a static setter, and provider
     * boot is comfortably before any request is handled.
     *
     * Off unless configured, because the wrong answer is dangerous in both
     * directions — see `config/fair.php`.
     */
    protected function trustConfiguredProxies(): void
    {
        $proxies = config('fair.trusted_proxies');

        if (blank($proxies)) {
            return;
        }

        TrustProxies::at(
            $proxies === '*' ? '*' : array_map(trim(...), explode(',', (string) $proxies)),
        );
    }
}
