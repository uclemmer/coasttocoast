<?php

use App\Enums\Audience;
use App\Enums\DeliveryStatus;
use App\Enums\MessageChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\EventInterest;
use App\Models\FaqItem;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\SponsorStaff;
use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('Payment', function () {
    it('casts method, status, money and the check date', function () {
        $payment = Payment::factory()->check()->create(['amount_cents' => 21500]);

        expect($payment->method)->toBe(PaymentMethod::Check)
            ->and($payment->status)->toBe(PaymentStatus::Succeeded)
            ->and($payment->amount_cents)->toBeInt()
            ->and($payment->check_received_on)->toBeInstanceOf(Carbon::class)
            ->and($payment->amountInDollars())->toBe(215.0);
    });

    it('carries no Stripe identifiers on a check', function () {
        // Leaving them populated would make a check look like a card payment
        // to anything querying by session id.
        $payment = Payment::factory()->check()->create();

        expect($payment->stripe_checkout_session_id)->toBeNull()
            ->and($payment->stripe_payment_intent_id)->toBeNull()
            ->and($payment->recorder)->not->toBeNull();
    });

    it('scopes to money actually collected', function () {
        Payment::factory()->create();
        Payment::factory()->pending()->create();
        Payment::factory()->failed()->create();
        Payment::factory()->refunded()->create();

        expect(Payment::query()->succeeded()->count())->toBe(1);
    });

    it('belongs to a registration', function () {
        $registration = Registration::factory()->create();

        expect(Payment::factory()->for($registration)->create()->registration->is($registration))->toBeTrue();
    });
});

describe('StripeWebhookEvent', function () {
    it('claims an unseen event and casts its payload', function () {
        $claimed = StripeWebhookEvent::claim('evt_1', 'checkout.session.completed', ['id' => 'evt_1']);

        expect($claimed)->not->toBeNull()
            ->and($claimed->payload)->toBe(['id' => 'evt_1'])
            ->and($claimed->processed_at)->toBeNull();
    });

    it('refuses to claim an event it has already seen', function () {
        // This is what makes a Stripe redelivery a no-op instead of a second
        // receipt.
        StripeWebhookEvent::claim('evt_1', 'checkout.session.completed', []);

        expect(StripeWebhookEvent::claim('evt_1', 'checkout.session.completed', []))->toBeNull()
            ->and(StripeWebhookEvent::query()->count())->toBe(1);
    });

    it('stamps the processed time', function () {
        $event = StripeWebhookEvent::claim('evt_2', 'charge.refunded', []);

        $event->markProcessed();

        expect($event->fresh()->processed_at)->toBeInstanceOf(Carbon::class);
    });
});

describe('EventInterest', function () {
    it('scopes to rows the announcement has not reached', function () {
        $waiting = EventInterest::factory()->create();
        EventInterest::factory()->notified()->create();

        expect(EventInterest::query()->unnotified()->pluck('id')->all())->toBe([$waiting->id]);
    });

    it('belongs to an event and tolerates a missing organization name', function () {
        $event = Event::factory()->create();
        $interest = EventInterest::factory()->withoutOrganizationName()->for($event)->create();

        expect($interest->event->is($event))->toBeTrue()
            ->and($interest->organization_name)->toBeNull();
    });
});

describe('Sponsor', function () {
    it('orders by the coordinator\'s position, then by name', function () {
        $third = Sponsor::factory()->ordered(2)->create(['name' => 'Alpha']);
        $first = Sponsor::factory()->ordered(0)->create(['name' => 'Zulu']);
        $second = Sponsor::factory()->ordered(1)->create(['name' => 'Mike']);

        expect(Sponsor::query()->ordered()->pluck('id')->all())
            ->toBe([$first->id, $second->id, $third->id]);
    });

    it('lists its staff in order', function () {
        $sponsor = Sponsor::factory()->create();
        $second = SponsorStaff::factory()->for($sponsor)->create(['sort_order' => 1]);
        $first = SponsorStaff::factory()->for($sponsor)->create(['sort_order' => 0]);

        expect($sponsor->staff->pluck('id')->all())->toBe([$first->id, $second->id])
            ->and($first->sponsor->is($sponsor))->toBeTrue();
    });
});

describe('FaqItem', function () {
    it('publishes in order and hides unpublished questions', function () {
        $second = FaqItem::factory()->ordered(1)->create();
        $first = FaqItem::factory()->ordered(0)->create();
        FaqItem::factory()->unpublished()->create();

        expect(FaqItem::query()->published()->pluck('id')->all())->toBe([$first->id, $second->id]);
    });
});

describe('Message', function () {
    it('casts the audience, channels and schedule', function () {
        $message = Message::factory()->withSms()->create();

        expect($message->audience)->toBeInstanceOf(Audience::class)
            ->and($message->channel_cases)->toBe([MessageChannel::Email, MessageChannel::Sms])
            ->and($message->usesChannel(MessageChannel::Sms))->toBeTrue()
            ->and($message->isSent())->toBeFalse();
    });

    it('reports email-only when SMS was not selected', function () {
        $message = Message::factory()->create();

        expect($message->usesChannel(MessageChannel::Sms))->toBeFalse()
            ->and($message->usesChannel(MessageChannel::Email))->toBeTrue();
    });

    it('falls back to the active fair when no reference event was chosen', function () {
        $active = Event::factory()->published()->create();
        $message = Message::factory()->create(['event_id' => null]);

        expect($message->referenceEvent()?->id)->toBe($active->id);
    });

    it('finds messages whose scheduled moment has arrived', function () {
        $due = Message::factory()->scheduledFor(Carbon::now()->subMinute())->create();
        Message::factory()->scheduledFor(Carbon::now()->addHour())->create();
        Message::factory()->scheduledFor(Carbon::now()->subMinute())->sent()->create();
        Message::factory()->create();

        expect(Message::query()->dueToSend()->pluck('id')->all())->toBe([$due->id]);
    });
});

describe('MessageRecipient', function () {
    it('gets a ULID key, because it rides out in a mail header', function () {
        $recipient = MessageRecipient::factory()->create();

        expect($recipient->id)->toBeString()->toHaveLength(26);
    });

    it('defaults to queued email and skipped SMS', function () {
        $recipient = MessageRecipient::factory()->create();

        expect($recipient->email_status)->toBe(DeliveryStatus::Pending)
            ->and($recipient->sms_status)->toBe(DeliveryStatus::Skipped);
    });

    it('falls back to the local status when no email log is linked', function () {
        $recipient = MessageRecipient::factory()->create(['email_status' => DeliveryStatus::Sent]);

        expect($recipient->resolvedEmailStatus())->toBe(DeliveryStatus::Sent);
    });

    it('derives an alphabetizing key from the organization snapshot', function () {
        // Same rule as the roster (doc 10, D-10-a), so the delivery table and
        // the public list file an institution in the same place.
        $recipient = MessageRecipient::factory()->create([
            'organization_name' => 'The University of Alabama at Birmingham',
        ]);

        expect($recipient->organization_sort_name)->toBe('university of alabama at birmingham');
    });

    it('leaves the sort key null when no organization was named', function () {
        // An empty string here would claim an organization named nothing, and
        // would be indistinguishable from one.
        $recipient = MessageRecipient::factory()->interestOnly()->create();

        expect($recipient->organization_sort_name)->toBeNull();
    });

    it('files a named interest-list signup with the other organizations', function () {
        // The interest form's organization field is optional, not absent, and
        // AudienceBuilder passes whatever was typed straight through. The
        // delivery table's "Interest list" label means "no name given" — it is
        // not a statement about where the recipient came from.
        $interest = EventInterest::factory()
            ->create(['organization_name' => 'The University of Alabama at Birmingham']);

        $recipient = MessageRecipient::factory()->create([
            'organization_id' => null,
            'user_id' => null,
            'organization_name' => $interest->organization_name,
        ]);

        expect($recipient->organization_sort_name)->toBe('university of alabama at birmingham');
    });

    it('derives the sort key from the frozen snapshot, not from the live organization', function () {
        // These rows record who was mailed. Renaming the organization afterwards
        // must not reorder a campaign that already went out.
        $organization = Organization::factory()->named('Aardvark College')->create();
        $recipient = MessageRecipient::factory()->create([
            'organization_id' => $organization->id,
            'organization_name' => 'Zebra College',
        ]);

        $organization->update(['name' => 'Aardvark University']);

        expect($recipient->fresh()->organization_sort_name)->toBe('zebra college');
    });

    it('carries no organization or account for an interest-list recipient', function () {
        $recipient = MessageRecipient::factory()->interestOnly()->create();

        expect($recipient->user_id)->toBeNull()
            ->and($recipient->organization_id)->toBeNull()
            ->and($recipient->email)->not->toBeEmpty();
    });

    it('carries an organization but no account for the generic fallback', function () {
        $recipient = MessageRecipient::factory()->generic()->create();

        expect($recipient->user_id)->toBeNull()
            ->and($recipient->organization_id)->not->toBeNull();
    });
});

describe('DeliveryStatus translation', function () {
    it('maps laravel-core email log statuses onto ours', function () {
        expect(DeliveryStatus::fromEmailLog('sending'))->toBe(DeliveryStatus::Sending)
            ->and(DeliveryStatus::fromEmailLog('sent'))->toBe(DeliveryStatus::Sent)
            ->and(DeliveryStatus::fromEmailLog('failed'))->toBe(DeliveryStatus::Failed);
    });

    it('degrades an unknown status rather than throwing', function () {
        // A delivery table has to render even if the package adds a status we
        // have not seen.
        expect(DeliveryStatus::fromEmailLog('bounced'))->toBe(DeliveryStatus::Pending)
            ->and(DeliveryStatus::fromEmailLog(null))->toBe(DeliveryStatus::Pending);
    });
});
