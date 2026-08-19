<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

/**
 * Every email this application sends, rendered through the one themed layout
 * (doc 07 §1) and put on the right Postmark stream (doc 04).
 *
 * Deliberately not Laravel's markdown mailables: the default markdown theme is
 * a second look competing with ours, and doc 07 rules it out. `->view()` on a
 * `MailMessage` renders our Blade and nothing else.
 */
trait RendersThemedMail
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function themed(string $view, string $subject, array $data = []): MailMessage
    {
        return (new MailMessage)
            ->subject($subject)
            ->view($view, $data)
            ->withSymfonyMessage(fn (Email $message) => $this->stampStream($message, $this->messageStream()));
    }

    /**
     * Transactional by default. Campaigns override this.
     *
     * Keeping the two apart is what stops a badly received bulk send from
     * damaging the deliverability of a receipt (doc 04).
     */
    protected function messageStream(): string
    {
        return (string) config('services.postmark.message_stream_id', 'outbound');
    }

    protected function stampStream(Email $message, string $stream): void
    {
        $headers = $message->getHeaders();

        // Replace rather than add: a duplicate header is a Postmark 422, and
        // a resend through core's EmailLog re-renders a message that may
        // already carry one.
        if ($headers->has('X-PM-Message-Stream')) {
            $headers->remove('X-PM-Message-Stream');
        }

        $headers->addTextHeader('X-PM-Message-Stream', $stream);
    }
}
