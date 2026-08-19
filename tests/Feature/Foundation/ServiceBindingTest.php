<?php

use App\Models\Registration;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeCheckoutService;
use App\Services\RegistrationService;
use App\Services\RosterService;
use App\Services\Sms\NullSms;
use App\Services\Sms\SmsService;
use App\Services\Sms\TwilioSms;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakePaymentGateway;

describe('SMS binding', function () {
    it('binds the null implementation when Twilio is not configured', function () {
        // This is the whole test suite's configuration, and local development's.
        // No test can send a real message by forgetting to fake something.
        config()->set('services.twilio', ['sid' => null, 'token' => null, 'from' => null]);
        app()->forgetInstance(SmsService::class);

        expect(app(SmsService::class))->toBeInstanceOf(NullSms::class);
    });

    it('binds the null implementation when only some credentials are present', function (array $credentials) {
        // A half-configured Twilio account must degrade to no SMS, not to a
        // client that throws on first use inside a queued notification.
        config()->set('services.twilio', $credentials);
        app()->forgetInstance(SmsService::class);

        expect(app(SmsService::class))->toBeInstanceOf(NullSms::class);
    })->with([
        'no from number' => [['sid' => 'AC123', 'token' => 'secret', 'from' => null]],
        'no token' => [['sid' => 'AC123', 'token' => null, 'from' => '+15551234567']],
        'no sid' => [['sid' => null, 'token' => 'secret', 'from' => '+15551234567']],
        'blank strings' => [['sid' => '', 'token' => '', 'from' => '']],
    ]);

    it('binds Twilio once every credential is present', function () {
        config()->set('services.twilio', [
            'sid' => 'AC'.str_repeat('0', 32),
            'token' => 'secret',
            'from' => '+15551234567',
        ]);
        app()->forgetInstance(SmsService::class);

        expect(app(SmsService::class))->toBeInstanceOf(TwilioSms::class);
    });

    it('is a singleton, so a test can inspect what was sent', function () {
        expect(app(SmsService::class))->toBe(app(SmsService::class));
    });
});

describe('NullSms', function () {
    it('records messages and logs them instead of sending', function () {
        Log::spy();

        /** @var NullSms $sms */
        $sms = app(SmsService::class);
        $sms->flush();

        $result = $sms->send('+15551234567', 'See you Thursday at 6:30.');

        expect($result->sent)->toBeTrue()
            ->and($result->error)->toBeNull()
            ->and($sms->sentMessages())->toHaveCount(1)
            ->and($sms->sentMessages()[0])->toBe([
                'to' => '+15551234567',
                'body' => 'See you Thursday at 6:30.',
            ]);

        Log::shouldHaveReceived('info')->once();
    });

    it('filters recorded messages by destination', function () {
        /** @var NullSms $sms */
        $sms = app(SmsService::class);
        $sms->flush();

        $sms->send('+15551110000', 'first');
        $sms->send('+15552220000', 'second');
        $sms->send('+15551110000', 'third');

        expect($sms->messagesTo('+15551110000'))->toHaveCount(2)
            ->and($sms->messagesTo('+15559990000'))->toBeEmpty();
    });
});

describe('payment gateway binding', function () {
    it('resolves the Stripe implementation', function () {
        // Not conditional on configuration: there is no safe silent fallback
        // for taking money, so a missing secret must fail at the point of use
        // rather than bind something that pretends to work.
        config()->set('services.stripe.secret', 'sk_test_123');
        app()->forgetInstance(PaymentGateway::class);

        expect(app(PaymentGateway::class))->toBeInstanceOf(StripeCheckoutService::class);
    });

    it('refuses a registration with nothing to charge before it ever calls Stripe', function () {
        // Card 1.4 pinned this as "not yet implemented"; card 4.1 implemented
        // it, and the guard that replaced the placeholder is worth more: a
        // registration priced at zero has no business at a gateway, and
        // charging $0 would paper over whichever caller skipped the free branch.
        config()->set('services.stripe.secret', 'sk_test_123');
        app()->forgetInstance(PaymentGateway::class);

        expect(fn () => app(PaymentGateway::class)->createSession(new Registration(['price_cents' => 0])))
            ->toThrow(RuntimeException::class, 'nothing to pay');
    });

    it('can be swapped for the fake the payment tests use', function () {
        $fake = new FakePaymentGateway;
        app()->instance(PaymentGateway::class, $fake);

        expect(app(PaymentGateway::class))->toBe($fake);
    });
});

describe('service shells', function () {
    it('resolves the registration and roster services', function () {
        expect(app(RegistrationService::class))->toBeInstanceOf(RegistrationService::class)
            ->and(app(RosterService::class))->toBeInstanceOf(RosterService::class);
    });
});

describe('integration config', function () {
    it('exposes the keys the vendors need', function () {
        expect(config('services.stripe'))->toHaveKeys(['key', 'secret', 'webhook_secret'])
            ->and(config('services.twilio'))->toHaveKeys(['sid', 'token', 'from'])
            // Laravel's postmark transport reads services.postmark.token.
            ->and(config('services.postmark'))->toHaveKeys(['token', 'message_stream_id', 'broadcast_stream_id']);
    });

    it('keeps transactional and campaign mail on separate Postmark streams', function () {
        // Separate streams are what stop a badly received bulk send from
        // damaging the deliverability of a receipt (doc 04).
        expect(config('services.postmark.message_stream_id'))
            ->not->toBe(config('services.postmark.broadcast_stream_id'));
    });

    it('exposes the fair contact block every surface shares', function () {
        expect(config('fair.contact'))->toHaveKeys([
            'name', 'email', 'phone', 'address_line1', 'city', 'state', 'postal_code',
        ])->and(config('fair.contact.email'))->not->toBeEmpty()
            // The postal address is a CAN-SPAM requirement for campaign mail,
            // not decoration (doc 07 §1).
            ->and(config('fair.contact.address_line1'))->not->toBeEmpty();
    });

    it('falls back to a real coordinator identity when the env keys are blank', function () {
        expect(config('fair.coordinator.email'))->not->toBeEmpty()
            ->and(config('fair.coordinator.name'))->not->toBeEmpty();
    });
});
