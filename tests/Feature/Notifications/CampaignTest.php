<?php

use App\Enums\Audience;
use App\Enums\DeliveryStatus;
use App\Enums\MessageChannel;
use App\Filament\Admin\Resources\MessageResource\Pages\CreateMessage;
use App\Filament\Admin\Resources\MessageResource\Pages\ListMessages;
use App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage;
use App\Jobs\SendEventBroadcast;
use App\Listeners\LinkEmailLogToRecipient;
use App\Models\Event as Fair;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\CampaignMessage;
use App\Services\AudienceBuilder;
use App\Services\Sms\NullSms;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Notification;
use UClemmer\LaravelCore\EmailLog\EmailLog;
use UClemmer\LaravelCore\Events\EmailLogged;

beforeEach(function () {
    $this->fair = Fair::factory()->published()->create();
    $this->school = Organization::factory()->named('Kenyon College')->create();
    $this->rep = User::factory()->rep($this->school)->create(['email' => 'dana@kenyon.example']);
    Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

    $this->message = Message::factory()
        ->to(Audience::ThisEventConfirmed)
        ->create(['event_id' => $this->fair->id, 'subject' => 'Parking and check-in']);
});

describe('sending', function () {
    it('freezes the resolved audience into recipient rows', function () {
        Notification::fake();

        SendEventBroadcast::dispatchSync($this->message);

        $recipient = $this->message->recipients()->firstOrFail();

        expect($this->message->recipients()->count())->toBe(1)
            ->and($recipient->email)->toBe('dana@kenyon.example')
            // Snapshots, so a later profile edit cannot rewrite the record of
            // who was mailed.
            ->and($recipient->organization_name)->toBe('Kenyon College')
            ->and($recipient->user_id)->toBe($this->rep->id)
            ->and($recipient->organization_id)->toBe($this->school->id);
    });

    it('sends one campaign notification per recipient', function () {
        Notification::fake();

        SendEventBroadcast::dispatchSync($this->message);

        Notification::assertSentOnDemandTimes(CampaignMessage::class, 1);
    });

    it('marks the message sent before fanning out, so a retry cannot double-send', function () {
        // If the process dies halfway through a hundred notifications, a retry
        // that re-resolved and re-sent would be far worse than one that stops.
        Notification::fake();

        SendEventBroadcast::dispatchSync($this->message);
        expect($this->message->refresh()->isSent())->toBeTrue();

        SendEventBroadcast::dispatchSync($this->message);

        expect($this->message->recipients()->count())->toBe(1);
        Notification::assertSentOnDemandTimes(CampaignMessage::class, 1);
    });

    it('resolves the audience when it fires, not when it was written', function () {
        // Doc 07 §2 rule 6. A note scheduled to "everyone confirmed" reaches
        // whoever is confirmed at that moment.
        Notification::fake();

        $latecomer = Organization::factory()->named('Late College')->create();
        User::factory()->rep($latecomer)->create();
        Registration::factory()->forEvent($this->fair)->forOrganization($latecomer)->create();

        SendEventBroadcast::dispatchSync($this->message);

        expect($this->message->recipients()->pluck('organization_name')->sort()->values()->all())
            ->toBe(['Kenyon College', 'Late College']);
    });

    it('records SMS as skipped for anyone who did not opt in', function () {
        // Better than leaving a row that looks stuck forever.
        Notification::fake();

        $message = Message::factory()->to(Audience::ThisEventConfirmed)->withSms()
            ->create(['event_id' => $this->fair->id]);

        SendEventBroadcast::dispatchSync($message);

        expect($message->recipients()->firstOrFail()->sms_status)->toBe(DeliveryStatus::Skipped);
    });

    it('texts a rep who did opt in', function () {
        $optedIn = User::factory()->rep($this->school)->smsOptedIn()->create();

        /** @var NullSms $sms */
        $sms = app(SmsService::class);
        $sms->flush();

        $message = Message::factory()->to(Audience::ThisEventConfirmed)->withSms('See you Thursday.')
            ->create(['event_id' => $this->fair->id]);

        SendEventBroadcast::dispatchSync($message);

        expect($sms->messagesTo($optedIn->phone))->toHaveCount(1)
            ->and($message->recipients()->where('user_id', $optedIn->id)->firstOrFail()->sms_status)
            ->toBe(DeliveryStatus::Pending);
    });
});

describe('delivery tracking', function () {
    it('links a log row to its recipient through the header', function () {
        // Doc 07 §4. This is what turns "we queued it" into a status the
        // coordinator can act on.
        $recipient = MessageRecipient::factory()->for($this->message)->create();

        $log = EmailLog::query()->create([
            'subject' => 'Parking and check-in',
            'headers' => [MessageRecipient::HEADER.': '.$recipient->getKey()],
            'status' => 'sent',
        ]);

        app(LinkEmailLogToRecipient::class)->handle(new EmailLogged($log));

        expect($recipient->refresh()->email_log_id)->toBe($log->getKey())
            // The log wins over the local column: it is what the transport
            // actually reported.
            ->and($recipient->resolvedEmailStatus())->toBe(DeliveryStatus::Sent);
    });

    it('handles headers given as a name => value map as well as as lines', function () {
        $recipient = MessageRecipient::factory()->for($this->message)->create();

        $log = EmailLog::query()->create([
            'subject' => 'x',
            'headers' => [MessageRecipient::HEADER => (string) $recipient->getKey()],
            'status' => 'sent',
        ]);

        app(LinkEmailLogToRecipient::class)->handle(new EmailLogged($log));

        expect($recipient->refresh()->email_log_id)->toBe($log->getKey());
    });

    it('does nothing for a log with no recipient header', function () {
        // Every transactional email in the app lands here. Not an error.
        $recipient = MessageRecipient::factory()->for($this->message)->create();

        $log = EmailLog::query()->create(['subject' => 'A receipt', 'headers' => [], 'status' => 'sent']);

        app(LinkEmailLogToRecipient::class)->handle(new EmailLogged($log));

        expect($recipient->refresh()->email_log_id)->toBeNull();
    });

    it('never lets a linking failure blow up, because the mail has already gone', function () {
        // A failure here would turn a delivered email into a failed job and a
        // retry that sends it again.
        $log = EmailLog::query()->create([
            'subject' => 'x',
            'headers' => [MessageRecipient::HEADER.': not-a-real-ulid'],
            'status' => 'sent',
        ]);

        expect(fn () => app(LinkEmailLogToRecipient::class)->handle(new EmailLogged($log)))
            ->not->toThrow(Throwable::class);
    });

    it('does not cross wires between two concurrent sends', function () {
        $a = MessageRecipient::factory()->for($this->message)->create();
        $b = MessageRecipient::factory()->for($this->message)->create();

        foreach ([$a, $b] as $recipient) {
            app(LinkEmailLogToRecipient::class)->handle(new EmailLogged(
                EmailLog::query()->create([
                    'subject' => 'x',
                    'headers' => [MessageRecipient::HEADER.': '.$recipient->getKey()],
                    'status' => 'sent',
                ]),
            ));
        }

        expect($a->refresh()->email_log_id)->not->toBe($b->refresh()->email_log_id)
            ->and($a->email_log_id)->not->toBeNull()
            ->and($b->email_log_id)->not->toBeNull();
    });

    it('falls back to the local column when no log is linked', function () {
        // SMS-only recipients, or an environment with email logging off.
        $recipient = MessageRecipient::factory()->for($this->message)
            ->create(['email_status' => DeliveryStatus::Failed]);

        expect($recipient->resolvedEmailStatus())->toBe(DeliveryStatus::Failed);
    });
});

describe('scheduled sends', function () {
    it('dispatches a campaign whose moment has arrived and leaves the rest alone', function () {
        Notification::fake();

        $due = Message::factory()->to(Audience::ThisEventConfirmed)
            ->scheduledFor(now()->subMinute())
            ->create(['event_id' => $this->fair->id]);
        $later = Message::factory()->to(Audience::ThisEventConfirmed)
            ->scheduledFor(now()->addHour())
            ->create(['event_id' => $this->fair->id]);

        $this->artisan('fair:send-scheduled-campaigns')->assertSuccessful();

        expect($due->refresh()->isSent())->toBeTrue()
            ->and($later->refresh()->isSent())->toBeFalse();
    });

    it('never picks up a campaign that has already gone', function () {
        Message::factory()->to(Audience::ThisEventConfirmed)
            ->scheduledFor(now()->subDay())->sent()
            ->create(['event_id' => $this->fair->id]);

        $this->artisan('fair:send-scheduled-campaigns')
            ->expectsOutputToContain('No campaigns due.')
            ->assertSuccessful();
    });
});

describe('the composer', function () {
    beforeEach(function () {
        usingAdminPanel();
        $this->actingAs(coordinator());
    });

    it('shows who a campaign would reach before it is sent', function () {
        // A count says whether it looks about right; the names say whether the
        // audience is the one she meant.
        livewire(ViewMessage::class, ['record' => $this->message->getRouteKey()])
            ->assertActionVisible('previewAudience')
            ->mountAction('previewAudience')
            ->assertSuccessful();

        // The modal body itself, rendered against the same resolved list the
        // action passes it.
        $recipients = app(AudienceBuilder::class)
            ->resolve($this->message->audience, $this->message->referenceEvent());

        expect(view('filament.admin.audience-preview', ['recipients' => $recipients])->render())
            ->toContain('dana@kenyon.example')
            ->toContain('Kenyon College');
    });

    it('composes a campaign, recording who wrote it', function () {
        // The create page 500ed on a Filament API that does not exist
        // (`Select::descriptions()`, which is a Radio method) and no resource
        // test noticed, because none of them opened it. The route smoke test
        // did. This pins it.
        livewire(CreateMessage::class)
            ->assertSuccessful()
            ->fillForm([
                'event_id' => $this->fair->id,
                'audience' => Audience::LapsedLastEvent->value,
                'subject' => 'We would love to see you again',
                'channels' => [MessageChannel::Email->value],
                'email_body' => 'Registration is open.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Message::query()->where('subject', 'We would love to see you again')->first())
            ->audience->toBe(Audience::LapsedLastEvent)
            ->created_by->not->toBeNull();
    });

    it('sends a test to the coordinator without recording it against the campaign', function () {
        Notification::fake();

        livewire(ViewMessage::class, ['record' => $this->message->getRouteKey()])
            ->callAction('testSend')
            ->assertNotified();

        Notification::assertSentOnDemandTimes(CampaignMessage::class, 1);

        // Rehearsals must not pollute the real delivery table.
        expect($this->message->recipients()->count())->toBe(0);
    });

    it('sends the campaign', function () {
        Notification::fake();

        livewire(ViewMessage::class, ['record' => $this->message->getRouteKey()])
            ->callAction('send');

        expect($this->message->refresh()->isSent())->toBeTrue()
            ->and($this->message->recipients()->count())->toBe(1);
    });

    it('hides the send button once it has gone, because there is no unsend', function () {
        $sent = Message::factory()->to(Audience::ThisEventConfirmed)->sent()
            ->create(['event_id' => $this->fair->id]);

        livewire(ViewMessage::class, ['record' => $sent->getRouteKey()])
            ->assertActionHidden('send');
    });

    it('refuses to edit or delete a sent campaign', function () {
        // It is the record of what a hundred schools were told, and the
        // delivery table only means anything if it has not changed since.
        $coordinator = coordinator();
        $sent = Message::factory()->sent()->create();
        $draft = Message::factory()->create();

        expect($coordinator->can('update', $sent))->toBeFalse()
            ->and($coordinator->can('delete', $sent))->toBeFalse()
            ->and($coordinator->can('update', $draft))->toBeTrue();
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(ListMessages::class)->assertForbidden();
    });
});
