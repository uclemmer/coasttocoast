<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\RegistrationNotAllowed;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Money that arrives in the post (card 4.2).
 *
 * The half of the payment story with no webhook: a coordinator opens an
 * envelope and types what she found. The recording and the confirmation happen
 * together, in one transaction, because a check marked received on a
 * registration that stayed `pending_payment` is the failure mode that gets a
 * organization turned away at the door.
 */
class CheckPaymentService
{
    public function __construct(
        protected RegistrationService $registrations,
    ) {}

    /**
     * Record a check and confirm the registration.
     *
     * The amount defaults to the registration's snapshot rather than being
     * required, because in the normal case the check is for exactly what was
     * asked. When it is not, the coordinator types the real figure and it is
     * recorded as received — this is a ledger of what happened, not of what
     * should have.
     */
    public function markReceived(
        Registration $registration,
        User $coordinator,
        ?string $checkNumber = null,
        ?Carbon $receivedOn = null,
        ?int $amountCents = null,
    ): Payment {
        if ($registration->payment_method !== PaymentMethod::Check) {
            throw RegistrationNotAllowed::notAwaitingACheck();
        }

        if ($registration->status !== RegistrationStatus::PendingPayment) {
            throw RegistrationNotAllowed::notAwaitingPayment();
        }

        return DB::transaction(function () use (
            $registration, $coordinator, $checkNumber, $receivedOn, $amountCents
        ): Payment {
            $payment = Payment::query()->create([
                'registration_id' => $registration->getKey(),
                'method' => PaymentMethod::Check,
                'status' => PaymentStatus::Succeeded,
                'amount_cents' => $amountCents ?? $registration->price_cents,
                'currency' => 'usd',
                'check_number' => $checkNumber,
                'check_received_on' => $receivedOn ?? Carbon::now(),
                'recorded_by' => $coordinator->getKey(),
            ]);

            // Same call the Stripe webhook makes, so both paths produce the
            // same events and therefore the same receipt.
            $this->registrations->confirmPayment($registration);

            return $payment;
        });
    }

    /**
     * Whether the coordinator paid attention to a short check.
     *
     * Not enforced — an organization that underpays by a dollar should not be blocked
     * from attending over it — but surfaced, because the alternative is
     * noticing in April.
     */
    public function isShort(Registration $registration, ?int $amountCents): bool
    {
        return $amountCents !== null && $amountCents < $registration->price_cents;
    }
}
