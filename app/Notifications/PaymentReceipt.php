<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Notifications\Concerns\RendersThemedMail;
use App\Services\ReceiptPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You are registered" (R4).
 *
 * Sent once a registration is confirmed, by whichever route got it there — the
 * Stripe webhook, a check the coordinator recorded, or a grant that covered
 * the fee. All three fire `RegistrationConfirmed`, so all three produce this
 * one email, and a school cannot tell from the receipt which path it took
 * except by what the receipt says.
 *
 * The PDF is attached rather than linked: a finance office needs the file, and
 * a link that expires or needs a login is a support call.
 */
class PaymentReceipt extends Notification implements ShouldQueue
{
    use Queueable, RendersThemedMail;

    public function __construct(
        public readonly Registration $registration,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $this->registration->loadMissing(['event', 'organization', 'grant']);

        $message = $this->themed(
            view: 'emails.notifications.payment-receipt',
            subject: __('Your registration for :event is confirmed', [
                'event' => (string) $this->registration->event?->name,
            ]),
            data: ['registration' => $this->registration],
        );

        $pdf = app(ReceiptPdf::class);

        if ($pdf->isAvailableFor($this->registration)) {
            $message->attachData(
                $pdf->render($this->registration),
                $pdf->filenameFor($this->registration),
                ['mime' => 'application/pdf'],
            );
        }

        return $message;
    }
}
