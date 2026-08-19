<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Notifications\Concerns\RendersThemedMail;
use App\Services\Payments\CheckPaymentForm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Here is where to send the check" (R4, card 4.2).
 *
 * The form is attached because the person who registers is often not the
 * person who writes the checks, and forwarding an attachment is one step where
 * an email with only a link gets lost.
 */
class RegistrationCheckInstructions extends Notification implements ShouldQueue
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

        $pdf = app(CheckPaymentForm::class);

        return $this->themed(
            view: 'emails.notifications.check-instructions',
            subject: __('How to pay for :event', ['event' => (string) $this->registration->event?->name]),
            data: ['registration' => $this->registration],
        )->attachData(
            $pdf->render($this->registration),
            $pdf->filenameFor($this->registration),
            ['mime' => 'application/pdf'],
        );
    }
}
