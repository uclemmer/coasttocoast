<?php

use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Permissions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;

describe('trusted proxies', function () {
    // The static outlives a single test, so every test here restores it.
    afterEach(fn () => TrustProxies::at([]));

    beforeEach(function () {
        Route::get('/_test_ip', fn (): string => (string) request()->ip());
    });

    it('ignores a forwarded address by default', function () {
        // A directly reachable host must not believe X-Forwarded-For. If it
        // did, anyone could mint a fresh throttle bucket per request and the
        // rate limits on the two public forms would silently stop existing.
        expect(config('fair.trusted_proxies'))->toBeEmpty();

        $this->get('/_test_ip', ['X-Forwarded-For' => '203.0.113.9'])
            ->assertOk()
            ->assertSee('127.0.0.1');
    });

    it('honours a forwarded address once a proxy is trusted', function () {
        // Behind a load balancer this is the difference between throttling per
        // visitor and throttling the whole internet as one.
        config(['fair.trusted_proxies' => '*']);
        (new AppServiceProvider($this->app))->boot();

        $this->get('/_test_ip', ['X-Forwarded-For' => '203.0.113.9'])
            ->assertOk()
            ->assertSee('203.0.113.9');
    });

    it('is read from config, not env, so config:cache cannot silently disable it', function () {
        // `config:cache` stops .env being loaded, so an env() call at the point
        // of use returns null in production -- the one environment this setting
        // is for. The value must come through the config file.
        expect(file_get_contents(base_path('config/fair.php')))
            ->toContain("'trusted_proxies' => env('TRUSTED_PROXIES')")
            ->and(file_get_contents(base_path('bootstrap/app.php')))
            ->not->toContain('TRUSTED_PROXIES');
    });
});

describe('response headers', function () {
    it('sets the headers that cost nothing and close something', function () {
        $response = $this->get('/');

        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
            ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    });

    it('sends HSTS only over HTTPS', function () {
        // Pinning a .test domain to https in a developer's browser for a year
        // is a genuinely annoying thing to do to somebody.
        //
        // Both schemes are named explicitly rather than leaning on whatever
        // `APP_URL` happens to be: this test passed for the wrong reason until
        // APP_URL moved to https (doc 10, D-8-f), because a relative `get('/')`
        // inherits the configured scheme.
        expect($this->get('http://coasttocoast.test/')->headers->has('Strict-Transport-Security'))
            ->toBeFalse();

        expect($this->get('https://coasttocoast.test/')->headers->get('Strict-Transport-Security'))
            ->toContain('max-age=31536000');
    });

    it('sets them on the webhook route too, which is outside the web group', function () {
        expect($this->postJson('/webhooks/stripe', [])->headers->get('X-Content-Type-Options'))
            ->toBe('nosniff');
    });
});

describe('the permission audit', function () {
    // Test-inventory item 13: tested against the actions, not against
    // navigation. A hidden menu item is not authorization.
    it('gives a coordinator every app permission and a rep none', function () {
        $coordinator = coordinator();
        $rep = User::factory()->rep()->create();

        foreach (array_keys(Permissions::permissions()) as $permission) {
            expect($coordinator->can($permission))->toBeTrue()
                ->and($rep->can($permission))->toBeFalse();
        }
    });

    it('refuses every admin model to a representative', function () {
        $rep = User::factory()->rep()->create();

        expect($rep->can('viewAny', Fair::class))->toBeFalse()
            ->and($rep->can('viewAny', Organization::class))->toBeFalse()
            ->and($rep->can('viewAny', Registration::class))->toBeFalse()
            ->and($rep->can('viewAny', Grant::class))->toBeFalse()
            ->and($rep->can('viewAny', Sponsor::class))->toBeFalse()
            ->and($rep->can('viewAny', Message::class))->toBeFalse();
    });

    it('never allows a registration or a grant to be deleted, by anybody', function () {
        // Both are audit records (doc 03). Cancel, deny or revoke instead.
        $coordinator = coordinator();

        expect($coordinator->can('delete', Registration::factory()->create()))->toBeFalse()
            ->and($coordinator->can('delete', Grant::factory()->create()))->toBeFalse();
    });

    it('separates recording money from managing registrations', function () {
        // The split matters the day somebody wants an assistant who can manage
        // the roster but not touch payments.
        $registration = Registration::factory()->create();
        $rep = User::factory()->rep()->create();

        expect(coordinator()->can('recordPayment', $registration))->toBeTrue()
            ->and($rep->can('recordPayment', $registration))->toBeFalse();
    });

    it('keeps a guest out of both authenticated panels', function () {
        $this->get('/admin')->assertRedirect();
        $this->get('/portal')->assertRedirect();
    });

    it('lets a guest read the whole public site', function () {
        $this->get('/')->assertOk();
        $this->get('/faq')->assertOk();
    });
});

describe('pruning', function () {
    it('trims processed Stripe ledger rows past their useful life', function () {
        $old = StripeWebhookEvent::factory()->processed()->create([
            'processed_at' => now()->subMonths(6),
        ]);
        $recent = StripeWebhookEvent::factory()->processed()->create();

        $this->artisan('fair:prune-stripe-events')->assertSuccessful();

        expect(StripeWebhookEvent::query()->find($old->id))->toBeNull()
            ->and(StripeWebhookEvent::query()->find($recent->id))->not->toBeNull();
    });

    it('keeps a delivery that never finished, however old', function () {
        // That row is a webhook that failed halfway — exactly the one worth
        // keeping.
        $unfinished = StripeWebhookEvent::factory()->create(['created_at' => now()->subYear()]);

        $this->artisan('fair:prune-stripe-events')->assertSuccessful();

        expect(StripeWebhookEvent::query()->find($unfinished->id))->not->toBeNull();
    });

    it('honours the 24-month promise on campaign recipient rows', function () {
        // N3. The message survives — subject, audience, when it went out. What
        // goes is the personal data.
        $message = Message::factory()->sent()->create();
        $old = $message->recipients()->create([
            'email' => 'old@example.edu',
            'created_at' => now()->subYears(3),
        ]);
        $recent = $message->recipients()->create(['email' => 'recent@example.edu']);

        $this->artisan('fair:prune-message-recipients')->assertSuccessful();

        expect($message->recipients()->find($old->id))->toBeNull()
            ->and($message->recipients()->find($recent->id))->not->toBeNull()
            ->and(Message::query()->find($message->id))->not->toBeNull();
    });

    it('schedules every pruning command the retention promises need', function () {
        $scheduled = collect(app(Schedule::class)->events())
            ->map(fn ($event): string => $event->command ?? '')
            ->implode(' ');

        expect($scheduled)
            ->toContain('postmaster:prune')
            ->toContain('core:prune-contact-submissions')
            ->toContain('fair:prune-message-recipients')
            ->toContain('fair:prune-stripe-events')
            ->toContain('fair:send-scheduled-campaigns');
    });
});

describe('core:doctor', function () {
    it('passes, so the deploy pipeline can gate on it', function () {
        // Catches jointly-wrong configuration — a contact form creating
        // accounts nothing can claim, an email log enabled with no store.
        $this->artisan('core:doctor')->assertSuccessful();
    });
});
