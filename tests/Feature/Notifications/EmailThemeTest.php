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

/**
 * The handoff's `email-template.html` (docs/16). Every assertion here exists
 * because the thing it pins failed silently once, or would.
 */
describe('the brand design', function () {
    it('sends in the fair green, not the framework blue it shipped with', function () {
        // `fair.brand.color_primary` sat on Laravel's stock #1d4ed8 from card
        // 6.0 until 2026-09-01, and every email went out blue, because the one
        // surface that reads it had no test that looked at a colour.
        expect(config('fair.brand.color_primary'))->toBe('#188042');

        Notification::route('mail', 'dana@kenyon.example')->notify(new PaymentReceipt($this->registration));

        expect((string) lastSentEmail()?->getHtmlBody())
            ->toContain('#188042')
            ->not->toContain('#1d4ed8');
    });

    it('shows the subject as a headline in the body', function () {
        // The design's body card is eyebrow, headline, copy. Before this the
        // subject reached the reader only in the inbox list.
        Notification::route('mail', 'dana@kenyon.example')->notify(new PaymentReceipt($this->registration));

        expect((string) lastSentEmail()?->getHtmlBody())
            ->toContain('font-size:28px')
            ->toContain('Registration confirmed');
    });

    it('puts the fair and the venue in the eyebrow when the message has one', function () {
        Notification::route('mail', 'someone@example.edu')
            ->notify(new RegistrationOpenAnnouncement($this->fair));

        expect((string) lastSentEmail()?->getHtmlBody())
            ->toContain($this->fair->starts_at->format('F j, Y').' · '.$this->fair->venue_name);
    });

    it('uses email-safe fonts only', function () {
        // The site self-hosts Montserrat, Caveat and Source Sans 3. A web font
        // in an inbox is unreliable at best and stripped at worst, so the
        // handoff specifies Arial and Georgia instead — and a Google Fonts
        // <link> here would leak the recipient to a third party on open, which
        // is the same objection the public pages answer (doc 10, D-8.1-a).
        Notification::route('mail', 'dana@kenyon.example')->notify(new PaymentReceipt($this->registration));

        $html = (string) lastSentEmail()?->getHtmlBody();

        expect($html)
            ->toContain('Georgia')
            ->toContain('Arial')
            ->not->toContain('Montserrat')
            ->not->toContain('Caveat')
            ->not->toContain('fonts.googleapis.com')
            ->not->toContain('/build/assets/');
    });

    it('offers no unsubscribe or preferences link, because neither route exists', function () {
        // The handoff's footer carries both. postmaster's subscription feature
        // has not landed, so wiring them would ship two dead links to every
        // recipient of a campaign. Delete this test when the routes exist —
        // its job is to keep the omission deliberate rather than forgotten.
        Notification::route('mail', 'someone@example.edu')
            ->notify(new RegistrationOpenAnnouncement($this->fair));

        expect((string) lastSentEmail()?->getHtmlBody())
            ->not->toContain('/unsubscribe')
            ->not->toContain('/preferences');
    });
});
