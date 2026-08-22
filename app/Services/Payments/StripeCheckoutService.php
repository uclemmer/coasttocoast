<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Registration;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Stripe Checkout — the hosted payment page (doc 04).
 *
 * Hosted rather than Elements on purpose: no card data ever reaches this
 * application, which keeps PCI scope at SAQ A. Do not add a custom card form.
 *
 * The amount comes from `$registration->price_cents` and from nowhere else.
 * That column was written by `RegistrationService` from `Event::priceFor()`,
 * so the figure Stripe charges, the figure the wizard showed and the figure on
 * the receipt are the same number by construction rather than by agreement (N1).
 */
class StripeCheckoutService implements PaymentGateway
{
    public function __construct(
        protected StripeClient $stripe,
    ) {}

    public function createSession(Registration $registration): CheckoutSession
    {
        if ($registration->price_cents <= 0) {
            // A grant made this free. It has no payment method, no payment row
            // and no business at a gateway — reaching here means a caller
            // skipped the free branch, and charging $0 would paper over it.
            throw new RuntimeException('A registration with nothing to pay must never reach the gateway.');
        }

        $registration->loadMissing(['event', 'organization']);

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => (string) $registration->getKey(),
            'customer_email' => $registration->rep_email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $registration->price_cents,
                    'product_data' => [
                        'name' => $registration->event?->name.' — registration',
                        'description' => $registration->organization?->name,
                    ],
                ],
            ]],
            // The webhook reads this. `client_reference_id` carries the same
            // value, but metadata survives on the PaymentIntent too, which is
            // what a refund event gives us.
            'metadata' => [
                'registration_id' => (string) $registration->getKey(),
                'organization_id' => (string) $registration->organization_id,
                'event_id' => (string) $registration->event_id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'registration_id' => (string) $registration->getKey(),
                ],
            ],
            'success_url' => $this->successUrl($registration),
            'cancel_url' => $this->cancelUrl(),
            'expires_at' => now()->addHours(24)->timestamp,
        ]);

        // Pending, not succeeded. The rep coming back from Stripe proves
        // nothing — the webhook is the source of truth (golden rule 3), and
        // this row exists so the webhook has something to find.
        Payment::query()->create([
            'registration_id' => $registration->getKey(),
            'method' => PaymentMethod::Stripe,
            'status' => PaymentStatus::Pending,
            'amount_cents' => $registration->price_cents,
            'currency' => 'usd',
            'stripe_checkout_session_id' => $session->id,
        ]);

        return new CheckoutSession(
            id: $session->id,
            url: (string) $session->url,
            amountCents: $registration->price_cents,
        );
    }

    public function refund(Payment $payment, ?int $amountCents = null): void
    {
        if ($payment->status !== PaymentStatus::Succeeded) {
            throw new RuntimeException('Only a settled payment can be refunded.');
        }

        if (blank($payment->stripe_payment_intent_id)) {
            throw new RuntimeException('This payment has no Stripe intent to refund. Record a manual refund instead.');
        }

        $this->stripe->refunds->create(array_filter([
            'payment_intent' => $payment->stripe_payment_intent_id,
            'amount' => $amountCents,
        ]));

        // Deliberately does NOT mark the payment refunded here. Stripe sends
        // `charge.refunded`, and letting that one handler own the transition
        // means an admin-initiated refund and one issued from the Stripe
        // dashboard leave the database in the same state.
    }

    /*
     * Where Stripe sends the rep back to.
     *
     * These were `RegistrationResource::getUrl(..., panel: 'rep')` until
     * 2026-08-21, when the Filament rep panel was deleted and rebuilt as the
     * Livewire screens at /portal. The import went stale in that change and
     * nothing caught it: no test reached either method, so the suite stayed
     * green while the live "pay by card" path had a fatal in it.
     *
     * Hence StripeReturnUrlTest, which calls both. A method that nothing calls
     * is a method that can be deleted out from under.
     */
    protected function successUrl(Registration $registration): string
    {
        return route('portal.registrations.show', $registration);
    }

    protected function cancelUrl(): string
    {
        return route('portal.registrations');
    }
}
