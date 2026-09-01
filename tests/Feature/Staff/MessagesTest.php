<?php

use App\Enums\Audience;
use App\Enums\MessageChannel;
use App\Livewire\Staff\Messages\Edit as EditMessage;
use App\Livewire\Staff\Messages\Index as MessageIndex;
use App\Livewire\Staff\Messages\Show as ShowMessage;
use App\Models\Event as Fair;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\CampaignMessage;
use Illuminate\Support\Facades\Notification;

/*
 * The staff campaign screens (docs/13).
 *
 * Ported from CampaignTest's `the composer` block. Everything above that block
 * in the original — sending, delivery tracking, scheduled sends — tests jobs
 * and services rather than the panel, and is untouched by this rebuild.
 */

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);

    $this->fair = Fair::factory()->published()->create();
    $this->organization = Organization::factory()->named('Kenyon College')->create();
    $this->rep = User::factory()->rep($this->organization)->create(['email' => 'dana@kenyon.example']);
    Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

    $this->message = Message::factory()
        ->to(Audience::ThisEventConfirmed)
        ->create(['event_id' => $this->fair->id, 'subject' => 'Parking and check-in']);
});

describe('the list', function () {
    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(MessageIndex::class)->assertForbidden();
    });

    it('says where each campaign is in its life', function () {
        $sent = Message::factory()->sent()->create();
        $draft = Message::factory()->create(['scheduled_for' => null]);

        $page = livewire(MessageIndex::class)->instance();

        expect($page->statusLine($sent))->toContain('Sent')
            ->and($page->statusLine($draft))->toBe('Draft');
    });
});

describe('composing', function () {
    it('composes a campaign, recording who wrote it', function () {
        /*
         * The Filament create page once 500ed on an API that does not exist
         * (`Select::descriptions()`, a Radio method) and no resource test
         * noticed, because none of them opened it. Mounting the component is
         * half of what this pins.
         */
        livewire(EditMessage::class)
            ->assertSuccessful()
            ->set('event_id', (string) $this->fair->id)
            ->set('audience', Audience::LapsedLastEvent->value)
            ->set('subject', 'We would love to see you again')
            ->set('channels', [MessageChannel::Email->value])
            ->set('email_body', 'Registration is open.')
            ->call('save')
            ->assertHasNoErrors();

        expect(Message::query()->where('subject', 'We would love to see you again')->first())
            ->audience->toBe(Audience::LapsedLastEvent)
            ->created_by->not->toBeNull();
    });

    it('counts the audience before anybody commits to it', function () {
        // A count says whether it looks about right before the campaign goes.
        $page = livewire(EditMessage::class)->set('audience', Audience::ThisEventConfirmed->value);

        expect($page->instance()->previewCount())->toBe('1');
    });

    it('says to choose an audience rather than showing a zero', function () {
        // Zero is a real and alarming answer; it should not be shown when the
        // question has not been asked.
        expect(livewire(EditMessage::class)->instance()->previewCount())->toBe('Choose an audience');
    });

    it('requires the body of every channel it is sending by', function () {
        livewire(EditMessage::class)
            ->set('audience', Audience::ThisEventConfirmed->value)
            ->set('subject', 'x')
            ->set('channels', [MessageChannel::Email->value, MessageChannel::Sms->value])
            ->call('save')
            ->assertHasErrors(['email_body', 'sms_body']);
    });

    it('does not require the body of a channel it is not sending by', function () {
        // The other half of the rule, and the half that breaks when visibility
        // and requiredness are written as two separate statements.
        livewire(EditMessage::class)
            ->set('audience', Audience::ThisEventConfirmed->value)
            ->set('subject', 'Email only')
            ->set('channels', [MessageChannel::Email->value])
            ->set('email_body', 'Body.')
            ->call('save')
            ->assertHasNoErrors();
    });

    it('requires an audience and a subject', function () {
        livewire(EditMessage::class)->call('save')->assertHasErrors(['audience', 'subject']);
    });

    it('names the fair select as the form labels it, not as a column', function () {
        /*
         * `event_id` is nullable here, so `required` never fires and the label
         * only shows through `exists` — which is why this test points the field
         * at a fair that does not exist rather than leaving it blank.
         */
        $errors = livewire(EditMessage::class)
            ->set('audience', Audience::ThisEventConfirmed->value)
            ->set('subject', 'Parking and check-in')
            ->set('event_id', '999999')
            ->call('save')
            ->errors();

        expect($errors->first('event_id'))->toBe('The selected reference fair is invalid.');
    });

    it('names the two bodies after their inputs, not after their columns', function () {
        // Labelled "Email" and "Text message"; they used to fail as
        // "email body" and "sms body". Both channels on so both are required.
        $errors = livewire(EditMessage::class)
            ->set('audience', Audience::ThisEventConfirmed->value)
            ->set('subject', 'Parking and check-in')
            ->set('channels', [MessageChannel::Email->value, MessageChannel::Sms->value])
            ->call('save')
            ->errors();

        expect($errors->first('email_body'))->toBe('The email field is required.')
            ->and($errors->first('sms_body'))->toBe('The text message field is required.');
    });

    it('calls the channel list a delivery method rather than "send by"', function () {
        /*
         * The one field that deliberately does NOT take its label. The checkbox
         * list is headed "Send by", and "the send by field is required" is not
         * English — the heading is not a noun for the thing being validated.
         * Pinned because the obvious "fix" is to make it match the label.
         */
        $errors = livewire(EditMessage::class)
            ->set('audience', Audience::ThisEventConfirmed->value)
            ->set('subject', 'Parking and check-in')
            ->set('channels', [])
            ->call('save')
            ->errors();

        expect($errors->first('channels'))->toBe('The delivery method field is required.');
    });

    it('refuses to open a sent campaign for editing', function () {
        // A form that cannot be saved should not be rendered.
        $sent = Message::factory()->sent()->create();

        livewire(EditMessage::class, ['message' => $sent])->assertForbidden();
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(EditMessage::class)->assertForbidden();
    });
});

describe('the campaign page', function () {
    it('shows who a campaign would reach before it is sent', function () {
        // A count says whether it looks about right; the names say whether the
        // audience is the one she meant. Filament put this behind a modal; it
        // is on the page now.
        $page = livewire(ShowMessage::class, ['message' => $this->message]);

        expect($page->instance()->audienceCount())->toBe(1);

        $page->assertSee('dana@kenyon.example');
    });

    it('sends a test to the coordinator without recording it against the campaign', function () {
        Notification::fake();

        livewire(ShowMessage::class, ['message' => $this->message])->call('sendTest');

        Notification::assertSentOnDemandTimes(CampaignMessage::class, 1);

        // Rehearsals must not pollute the real delivery table.
        expect($this->message->recipients()->count())->toBe(0);
    });

    it('sends the campaign', function () {
        Notification::fake();

        livewire(ShowMessage::class, ['message' => $this->message])->call('send');

        expect($this->message->refresh()->isSent())->toBeTrue()
            ->and($this->message->recipients()->count())->toBe(1);
    });

    it('refuses a second send, because there is no unsend', function () {
        // Belt and braces against a stale tab: a second send would mail a
        // hundred organizations twice.
        Notification::fake();

        $sent = Message::factory()->to(Audience::ThisEventConfirmed)->sent()
            ->create(['event_id' => $this->fair->id]);

        livewire(ShowMessage::class, ['message' => $sent])
            ->call('send')
            ->assertDispatched('ui-toast', fn (string $e, array $p): bool => $p['variant'] === 'danger');

        expect($sent->refresh()->recipients()->count())->toBe(0);
    });

    it('refuses to edit or delete a sent campaign', function () {
        // It is the record of what a hundred organizations were told, and the
        // delivery table only means anything if it has not changed since.
        $sent = Message::factory()->sent()->create();
        $draft = Message::factory()->create();

        expect($this->coordinator->can('update', $sent))->toBeFalse()
            ->and($this->coordinator->can('delete', $sent))->toBeFalse()
            ->and($this->coordinator->can('update', $draft))->toBeTrue();
    });

    it('refuses to remove a sent campaign from the list', function () {
        $sent = Message::factory()->sent()->create();

        livewire(MessageIndex::class)
            ->call('confirmDelete', $sent->id)
            ->call('delete')
            ->assertForbidden();

        expect(Message::query()->whereKey($sent->id)->exists())->toBeTrue();
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(ShowMessage::class, ['message' => $this->message])->assertForbidden();
    });
});
