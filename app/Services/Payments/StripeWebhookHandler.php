<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\StripeWebhookEvent;
use App\Services\RegistrationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * What Stripe's webhooks actually do to this database (doc 04).
 *
 * Separated from the controller so it can be tested without forging
 * signatures, and so the interesting decisions sit in one readable place.
 *
 * Two rules govern everything here:
 *
 *  1. **Idempotency first.** The ledger row is claimed before any work. Stripe
 *     redelivers until it gets a 2xx, and a second `checkout.session.completed`
 *     must not send a second receipt.
 *  2. **The webhook is the source of truth**, not the browser returning from
 *     Checkout (golden rule 3). Nothing else in the app confirms a card payment.
 */
class StripeWebhookHandler
{
    public function __construct(
        protected RegistrationService $registrations,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return bool whether this delivery did any work (false = already seen)
     */
    public function handle(string $eventId, string $type, array $payload): bool
    {
        $ledger = StripeWebhookEvent::claim($eventId, $type, $payload);

        if (! $ledger instanceof StripeWebhookEvent) {
            // Seen before. Acknowledge and do nothing — this is the redelivery
            // case, and it is the normal one.
            return false;
        }

        $object = $payload['data']['object'] ?? [];

        match ($type) {
            'checkout.session.completed' => $this->completed(is_array($object) ? $object : []),
            'checkout.session.expired' => $this->expired(is_array($object) ? $object : []),
            'charge.refunded' => $this->refunded(is_array($object) ? $object : []),
            default => null,
        };

        $ledger->markProcessed();

        return true;
    }

    /**
     * The money arrived.
     *
     * @param  array<string, mixed>  $session
     */
    protected function completed(array $session): void
    {
        $payment = $this->paymentForSession($session);
        $registration = $payment?->registration ?? $this->registrationFromMetadata($session);

        if (! $registration instanceof Registration) {
            Log::warning('Stripe checkout completed for an unknown registration.', [
                'session' => $session['id'] ?? null,
            ]);

            return;
        }

        $paidCents = (int) ($session['amount_total'] ?? 0);

        if ($paidCents !== $registration->price_cents) {
            /*
             * Amount mismatch. Flag; do NOT confirm.
             *
             * The only ways to get here are a tampered session or a bug in our
             * own pricing, and both mean the figure the organization agreed to and
             * the figure that moved are different. Confirming would bless it;
             * the coordinator has to look.
             */
            $this->flagMismatch($registration, $payment, $paidCents);

            return;
        }

        $payment?->forceFill([
            'status' => PaymentStatus::Succeeded,
            'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
        ])->save();

        // Idempotent on its own account too, so belt and braces with the ledger.
        $this->registrations->confirmPayment($registration);
    }

    /**
     * The session timed out without payment.
     *
     * The registration deliberately stays `pending_payment`: the organization still
     * holds its place and the "pay now" button on the portal still works. Only
     * the attempt failed.
     *
     * @param  array<string, mixed>  $session
     */
    protected function expired(array $session): void
    {
        $this->paymentForSession($session)?->forceFill(['status' => PaymentStatus::Failed])->save();
    }

    /**
     * Money went back.
     *
     * Owned here rather than by the admin action, so a refund issued from the
     * Stripe dashboard and one issued from our panel leave the database in the
     * same state. A partial refund moves the payment but NOT the registration:
     * the organization is still coming, it just paid less.
     *
     * @param  array<string, mixed>  $charge
     */
    protected function refunded(array $charge): void
    {
        $intentId = $charge['payment_intent'] ?? null;

        $payment = blank($intentId)
            ? null
            : Payment::query()->where('stripe_payment_intent_id', $intentId)->latest('id')->first();

        if (! $payment instanceof Payment) {
            Log::warning('Stripe refund for an unknown payment.', ['payment_intent' => $intentId]);

            return;
        }

        $refunded = (int) ($charge['amount_refunded'] ?? 0);
        $isFull = $refunded >= $payment->amount_cents;

        $payment->forceFill([
            'status' => $isFull ? PaymentStatus::Refunded : PaymentStatus::Succeeded,
        ])->save();

        if (! $isFull) {
            return;
        }

        $payment->registration?->forceFill([
            'status' => RegistrationStatus::Refunded,
            // Drops the organization off the public roster, which is the point: a
            // refunded registration is one that is not attending.
            'show_on_roster' => false,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function paymentForSession(array $session): ?Payment
    {
        $sessionId = $session['id'] ?? null;

        return blank($sessionId)
            ? null
            : Payment::query()->where('stripe_checkout_session_id', $sessionId)->first();
    }

    /**
     * Fall back to the metadata when no payment row matches — a session opened
     * by an older deploy, or one created out of band.
     *
     * @param  array<string, mixed>  $session
     */
    protected function registrationFromMetadata(array $session): ?Registration
    {
        $id = $session['metadata']['registration_id'] ?? $session['client_reference_id'] ?? null;

        return blank($id) ? null : Registration::query()->find($id);
    }

    protected function flagMismatch(Registration $registration, ?Payment $payment, int $paidCents): void
    {
        $note = sprintf(
            '%s — PAYMENT MISMATCH: Stripe reported %s against %s owed. Not confirmed; check before the fair.',
            Carbon::now()->toDateString(),
            number_format($paidCents / 100, 2),
            number_format($registration->price_cents / 100, 2),
        );

        $registration->forceFill([
            'notes' => blank($registration->notes) ? $note : $registration->notes."\n".$note,
        ])->save();

        $payment?->forceFill(['status' => PaymentStatus::Failed])->save();

        Log::error('Stripe payment amount did not match the registration snapshot.', [
            'registration_id' => $registration->getKey(),
            'expected_cents' => $registration->price_cents,
            'paid_cents' => $paidCents,
        ]);
    }
}
