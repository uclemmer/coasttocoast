<?php

namespace App\Listeners;

use App\Enums\PaymentMethod;
use App\Events\RegistrationConfirmed;
use App\Events\RegistrationCreated;
use App\Models\Registration;
use App\Notifications\Admin\AdminAlert;
use App\Notifications\PaymentReceipt;
use App\Notifications\RegistrationCheckInstructions;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;

/**
 * The comms matrix for registrations (R4, card 6.1) — the listeners
 * `RegistrationService` fires into (doc 10, D-2.3-a).
 *
 * Mail goes to the **fair contact on the registration**, not to the account
 * holder. They are usually the same person and sometimes deliberately not: the
 * wizard asks who is staffing the table precisely so a registration made by a
 * director for a colleague reaches the colleague.
 */
class SendRegistrationNotifications
{
    public function created(RegistrationCreated $event): void
    {
        $registration = $event->registration->loadMissing(['event', 'organization', 'grant']);

        // Only the check path needs instructions. A card payer is already at
        // Stripe, and a free registration has nothing to pay.
        if ($registration->payment_method === PaymentMethod::Check) {
            Notification::route('mail', $registration->rep_email)
                ->notify(new RegistrationCheckInstructions($registration));
        }

        $this->alertCoordinator(
            subject: __('New registration: :organization', [
                'organization' => (string) $registration->organization?->name,
            ]),
            headline: __('An organization has registered'),
            rows: [
                __('Organization') => $registration->organization?->name,
                __('Fair') => $registration->event?->name,
                __('Contact') => $registration->rep_name.' <'.$registration->rep_email.'>',
                __('Amount') => Money::format($registration->price_cents),
                __('Paying by') => $registration->payment_method?->getLabel() ?? __('Covered by a grant'),
                __('Fee assistance') => $registration->grant?->benefitSummary(),
            ],
            // No SMS: a registration is good news that can wait for morning.
            // Money arriving is what wakes somebody up.
            smsBody: null,
        );
    }

    public function confirmed(RegistrationConfirmed $event): void
    {
        $registration = $event->registration->loadMissing(['event', 'organization', 'grant']);

        Notification::route('mail', $registration->rep_email)
            ->notify(new PaymentReceipt($registration));

        $this->alertCoordinator(
            subject: __('Payment received: :organization', [
                'organization' => (string) $registration->organization?->name,
            ]),
            headline: __('A registration has been confirmed'),
            rows: [
                __('Organization') => $registration->organization?->name,
                __('Fair') => $registration->event?->name,
                __('Amount') => Money::format($registration->price_cents),
                __('Paid by') => $registration->payment_method?->getLabel() ?? __('Covered by a grant'),
            ],
            smsBody: __(':amount from :organization for :event.', [
                'amount' => Money::format($registration->price_cents),
                'organization' => (string) $registration->organization?->name,
                'event' => (string) $registration->event?->name,
            ]),
        );
    }

    /**
     * @param  array<string, string|null>  $rows
     */
    protected function alertCoordinator(
        string $subject,
        string $headline,
        array $rows,
        ?string $smsBody = null,
    ): void {
        AdminAlerts::send(new AdminAlert(
            subject: $subject,
            headline: $headline,
            rows: $rows,
            url: url('/admin/registrations'),
            linkLabel: __('Open registrations'),
            smsBody: $smsBody,
        ));
    }

    /**
     * Kept for the test suite and for anything that needs the fair contact
     * without going through a notification.
     */
    public static function contactFor(Registration $registration): string
    {
        return $registration->rep_email;
    }
}
