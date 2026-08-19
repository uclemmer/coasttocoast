<?php

namespace App\Http\Controllers;

use App\Services\Payments\StripeWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;

/**
 * `POST /webhooks/stripe` (doc 04).
 *
 * Thin on purpose. Three jobs: prove the request came from Stripe, hand it to
 * the handler, and answer 200 so Stripe stops retrying. Everything that
 * decides anything lives in `StripeWebhookHandler`, where it can be tested
 * without forging signatures.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookHandler $handler): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if (blank($secret)) {
            // Without a secret every caller is "Stripe", and anyone who can
            // reach this URL can confirm a registration. Refuse rather than
            // degrade.
            report(new \RuntimeException('Stripe webhook secret is not configured; refusing all deliveries.'));

            return response('Webhook not configured.', 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            return response('Invalid signature.', 400);
        }

        try {
            $handler->handle($event->id, $event->type, $event->toArray());
        } catch (Throwable $e) {
            // Reported, not re-thrown. A 500 makes Stripe retry, and a handler
            // that fails deterministically would then be retried for three
            // days. The ledger row records that this event was seen, and the
            // exception is in the log for a human.
            report($e);
        }

        return response('', 200);
    }
}
