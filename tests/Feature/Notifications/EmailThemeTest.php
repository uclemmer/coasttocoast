<?php

use App\Models\Event as Fair;
use App\Models\Organization;
use App\Models\Registration;
use App\Notifications\PaymentReceipt;
use App\Notifications\RegistrationOpenAnnouncement;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mime\Email;

/**
 * Note the transport. These tests use `array` rather than `Mail::fake()` on
 * purpose (doc 06): faking replaces the mailer entirely, and laravel-core's
 * laravel-postmaster's capture listeners hang off the real `MessageSending`/`MessageSent`
 * events. Faking would test that we called something, not that anything
 * rendered or was logged.
 */
beforeEach(function () {
    config()->set('mail.default', 'array');
    Mail::mailer('array')->getSymfonyTransport()->flush();

    $this->fair = Fair::factory()->published()->priced(21500)->create(['name' => 'College Fair 2027']);
    $this->school = Organization::factory()->named('Kenyon College')->create();
    $this->registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->school)
        ->create(['rep_email' => 'dana@kenyon.example', 'price_cents' => 21500]);
});

function lastSentEmail(): ?Email
{
    // `messages()` hands back a Collection, not an array — `end()` on one
    // returns false and takes the null-safe operator with it.
    $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last();

    return $sent?->getOriginalMessage();
}

describe('the themed layout', function () {
    it('renders the header, the body and the fair contact block', function () {
        Notification::route('mail', 'dana@kenyon.example')->notify(new PaymentReceipt($this->registration));

        $html = (string) lastSentEmail()?->getHtmlBody();

        expect($html)
            ->toContain('Kenyon College')
            ->toContain('College Fair 2027')
            ->toContain('$215.00')
            // The contact block, from config/fair.php — the same source the
            // public footer and the printed forms use.
            ->toContain((string) config('fair.contact.email'))
            ->toContain((string) config('fair.contact.address_line1'));
    });

    it('carries an inbox preview line', function () {
        Notification::route('mail', 'dana@kenyon.example')->notify(new PaymentReceipt($this->registration));

        expect((string) lastSentEmail()?->getHtmlBody())->toContain('Your place at College Fair 2027 is confirmed.');
    });

    it('leaves the CAN-SPAM explanation off transactional mail', function () {
        // A receipt is not promotional, and the line would be a lie on one.
        Notification::route('mail', 'dana@kenyon.example')->notify(new PaymentReceipt($this->registration));

        expect((string) lastSentEmail()?->getHtmlBody())
            ->not->toContain('You are receiving this because your institution registered');
    });

    it('puts it on campaign mail', function () {
        Notification::route('mail', 'someone@example.edu')
            ->notify(new RegistrationOpenAnnouncement($this->fair));

        expect((string) lastSentEmail()?->getHtmlBody())
            ->toContain('You are receiving this because your institution registered')
            // The physical address is the other half of the requirement.
            ->toContain((string) config('fair.contact.address_line1'));
    });

    it('attaches the receipt PDF rather than linking to it', function () {
        // A finance office needs the file; a link that expires or needs a
        // login is a support call.
        Notification::route('mail', 'dana@kenyon.example')->notify(new PaymentReceipt($this->registration));

        $attachments = lastSentEmail()?->getAttachments() ?? [];

        expect($attachments)->toHaveCount(1)
            ->and($attachments[0]->getBody())->toStartWith('%PDF-');
    });
});

describe('Postmark message streams', function () {
    it('puts transactional mail on the outbound stream', function () {
        Notification::route('mail', 'dana@kenyon.example')->notify(new PaymentReceipt($this->registration));

        expect(lastSentEmail()?->getHeaders()->get('X-PM-Message-Stream')?->getBodyAsString())
            ->toContain(config('services.postmark.message_stream_id'));
    });

    it('puts campaigns on the broadcast stream', function () {
        // Keeping them apart is what stops a badly received bulk send from
        // damaging the deliverability of a receipt.
        Notification::route('mail', 'someone@example.edu')
            ->notify(new RegistrationOpenAnnouncement($this->fair));

        expect(lastSentEmail()?->getHeaders()->get('X-PM-Message-Stream')?->getBodyAsString())
            ->toContain(config('services.postmark.broadcast_stream_id'));
    });

    it('sets the stream header exactly once', function () {
        // A duplicate is a Postmark 422, and a resend through the message log
        // re-renders a message that may already carry one.
        Notification::route('mail', 'dana@kenyon.example')->notify(new PaymentReceipt($this->registration));

        expect(iterator_to_array(lastSentEmail()?->getHeaders()->all('X-PM-Message-Stream')))->toHaveCount(1);
    });
});

describe('the laravel-core override', function () {
    it('themes package mail through our layout', function () {
        // Doc 07 §1, "one theme, two entry points". A contact receipt and a
        // registration receipt should look like the same organization.
        $rendered = view('core::components.mail.contact.layout', [
            'slot' => new HtmlString('<p>A message from the contact form.</p>'),
            'title' => 'Thanks for getting in touch',
        ])->render();

        expect($rendered)
            ->toContain('A message from the contact form.')
            // Ours, not the package's: the package layout has no contact block.
            ->toContain((string) config('fair.contact.email'))
            ->toContain('max-width:600px');
    });
});
