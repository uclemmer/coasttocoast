<?php

use App\Enums\Audience;
use App\Models\Event as Fair;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\Organization;
use App\Notifications\CampaignMessage;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use UClemmer\LaravelPostmaster\Mail\MessageClass;
use UClemmer\LaravelPostmaster\Mail\Streams;
use UClemmer\LaravelPostmaster\Suppression\Suppression;
use UClemmer\LaravelPostmaster\Suppression\SuppressionReason;

/*
 * Does an unsubscribe still stop a campaign?
 *
 * postmaster 0.2.0 stopped unsubscribes, spam complaints and soft bounces from
 * refusing TRANSACTIONAL mail, and classifies by the `X-PM-Message-Stream`
 * header — unstamped counts as transactional. Its changelog warns that a host
 * sending campaigns outside the package's own broadcast path will deliver them
 * to people who left.
 *
 * This app does send its own campaigns (SendEventBroadcast -> CampaignMessage),
 * so the warning applies to it by construction and the property is worth
 * pinning rather than reasoning about. It had no suppression test at all.
 */

beforeEach(function () {
    // The array transport still fires MessageSending, which is where the
    // package's guard hangs. Mail::fake() and Notification::fake() both short
    // out before it and would prove nothing.
    config()->set('mail.default', 'array');

    $this->fair = Fair::factory()->published()->create();
    $this->organization = Organization::factory()->named('Kenyon College')->create();

    $this->campaign = Message::factory()->to(Audience::ThisEventConfirmed)->create([
        'event_id' => $this->fair->id,
        'subject' => 'Parking and check-in',
        'email_body' => 'The garage on Carter Street is free after five.',
    ]);
});

function sendCampaignTo(string $address): void
{
    $recipient = MessageRecipient::factory()->create([
        'message_id' => test()->campaign->id,
        'organization_id' => test()->organization->id,
        'email' => $address,
    ]);

    Notification::route('mail', $address)
        ->notify(new CampaignMessage(test()->campaign, $recipient));
}

/** @return array<int, Email> */
function sentEmails(): array
{
    return array_map(
        fn ($sent) => $sent->getOriginalMessage(),
        app('mailer')->getSymfonyTransport()->messages()->all(),
    );
}

it('stamps its campaigns with the broadcast stream', function () {
    sendCampaignTo('dana@kenyon.example');

    $email = sentEmails()[0];

    expect($email->getHeaders()->get(Streams::HEADER)?->getBodyAsString())
        ->toBe(Streams::broadcast());
});

it('classifies its campaigns as marketing, not transactional', function () {
    // The whole question. If this is Transactional, an unsubscribe stops
    // refusing it and the next campaign reaches everyone who ever left.
    sendCampaignTo('dana@kenyon.example');

    expect(MessageClass::of(sentEmails()[0]))->toBe(MessageClass::Marketing);
});

it('refuses a campaign to somebody who unsubscribed', function () {
    Suppression::suppress('dana@kenyon.example', SuppressionReason::Unsubscribe);

    sendCampaignTo('dana@kenyon.example');

    expect(sentEmails())->toBe([]);
});

it('still delivers a campaign to everybody else', function () {
    // The negative above has to be able to fail: a guard that refuses
    // everything would pass it.
    Suppression::suppress('someone.else@kenyon.example', SuppressionReason::Unsubscribe);

    sendCampaignTo('dana@kenyon.example');

    expect(sentEmails())->toHaveCount(1);
});

it('lets transactional mail through to the same address, which is the 0.2.0 change', function () {
    Suppression::suppress('dana@kenyon.example', SuppressionReason::Unsubscribe);

    expect(Suppression::refuses('dana@kenyon.example', MessageClass::Marketing))->toBeTrue()
        ->and(Suppression::refuses('dana@kenyon.example', MessageClass::Transactional))->toBeFalse();
});

it('still refuses everything to a hard bounce', function () {
    Suppression::suppress('gone@kenyon.example', SuppressionReason::HardBounce);

    expect(Suppression::refuses('gone@kenyon.example', MessageClass::Marketing))->toBeTrue()
        ->and(Suppression::refuses('gone@kenyon.example', MessageClass::Transactional))->toBeTrue();
});

/*
 * The coupling that keeps the above true, and is not enforced anywhere.
 *
 * The app stamps `services.postmark.broadcast_stream_id`; postmaster classifies
 * against `postmaster.streams.broadcast` plus `streams.marketing`. Two separate
 * keys, in two separate files, owned by two separate packages — identical today
 * only because both default to 'broadcast'.
 *
 * Renaming the Postmark stream (a normal thing to do; Postmark lets you call it
 * anything) silently reclassifies every campaign as transactional, at which
 * point unsubscribes stop blocking them. No error, no failing test, and the
 * screens all look right.
 */
it('breaks if the two stream config keys drift apart', function () {
    config()->set('services.postmark.broadcast_stream_id', 'campaigns');

    Suppression::suppress('dana@kenyon.example', SuppressionReason::Unsubscribe);
    sendCampaignTo('dana@kenyon.example');

    // This is the bug, asserted so its shape is on record: a campaign stamped
    // with a stream postmaster does not know is transactional, and reaches
    // somebody who unsubscribed.
    expect(MessageClass::of(sentEmails()[0]))->toBe(MessageClass::Transactional)
        ->and(sentEmails())->toHaveCount(1);
});

it('names the app stream in postmaster\'s marketing list, so drift cannot happen', function () {
    /*
     * The fix for the test above. config/postmaster.php now reads
     * POSTMARK_BROADCAST_STREAM — the same env var the app stamps with — so
     * renaming the Postmark stream moves both halves at once.
     *
     * Asserted through Streams::isMarketing() rather than by comparing the two
     * config values, because that function is what actually decides, and it
     * folds in the marketing list a host might add later.
     */
    expect(Streams::isMarketing((string) config('services.postmark.broadcast_stream_id', 'broadcast')))
        ->toBeTrue();
});
