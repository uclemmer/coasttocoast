<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Events\RegistrationCancelled;
use App\Events\RegistrationConfirmed;
use App\Events\RegistrationCreated;
use App\Exceptions\RegistrationNotAllowed;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only thing that may create, confirm or cancel a registration (doc 02
 * convention 1).
 *
 * Filament resources, the portal wizard, the Stripe webhook and the admin
 * actions all come through here, because none of these rules can be enforced
 * by a model that anyone may `create()`:
 *
 *  - no second non-cancelled registration for the same organization and fair (R2.7);
 *  - the acting rep must be an ACTIVE member of that organization (D9);
 *  - `price_cents` is snapshotted from `Event::priceFor()`, never from input (N1);
 *  - a price of zero confirms immediately, with no payment method and no gateway;
 *  - registration is refused when the window is shut, the event is
 *    unpublished, or the room is full.
 *
 * It sends no mail. It fires domain events, and card 6.1 hangs the comms matrix
 * off them — see docs/10-implementation-decisions.md D-2.3-a.
 */
class RegistrationService
{
    /**
     * A representative registering their own organization through the portal.
     *
     * Every gate applies here. The coordinator's equivalent is
     * `createManualEntry()`, which is a separate method precisely so that
     * "skip the membership check" is something a caller has to ask for by
     * name rather than something that happens when an argument is null.
     *
     * @param  array{rep_name?: string, rep_email?: string, rep_phone?: string|null}  $contact
     *                                                                                          overrides for this fair's contact details; falls back to the rep's account
     */
    public function create(
        Event $event,
        Organization $organization,
        User $rep,
        ?PaymentMethod $method = null,
        array $contact = [],
    ): Registration {
        if ($rep->organization_id !== $organization->getKey()) {
            throw RegistrationNotAllowed::repBelongsToAnotherOrganization();
        }

        if (! $rep->actsForOrganization()) {
            throw RegistrationNotAllowed::repIsNotAnActiveMember($organization);
        }

        if (! $event->isRegistrationOpen()) {
            throw RegistrationNotAllowed::registrationIsClosed($event);
        }

        return $this->store(
            event: $event,
            organization: $organization,
            rep: $rep,
            method: $method,
            contact: array_replace([
                'rep_name' => $rep->name,
                'rep_email' => $rep->email,
                'rep_phone' => $rep->phone,
            ], array_filter($contact, fn ($value): bool => $value !== null)),
        );
    }

    /**
     * The coordinator entering a registration on someone's behalf — a phone
     * call, a form that arrived in the post, an imported historical row.
     *
     * Skips the membership gate (there is no acting rep) and the registration
     * window (a coordinator taking a late registration is doing her job, not
     * circumventing a rule). Still refuses a duplicate and still snapshots the
     * grant-aware price, because those two protect the data rather than the
     * process.
     *
     * @param  array{rep_name: string, rep_email: string, rep_phone?: string|null}  $contact
     */
    public function createManualEntry(
        Event $event,
        Organization $organization,
        array $contact,
        ?PaymentMethod $method = null,
        ?string $notes = null,
    ): Registration {
        return $this->store(
            event: $event,
            organization: $organization,
            rep: null,
            method: $method,
            contact: $contact,
            notes: $notes,
            enforceCapacity: false,
        );
    }

    /**
     * Settle a registration: the webhook saw the money, or the coordinator
     * marked a check received.
     *
     * Idempotent by design. Stripe redelivers a webhook until it gets a 2xx,
     * and a second `RegistrationConfirmed` means a second receipt — which is
     * exactly the kind of thing an organization notices and the coordinator has to
     * apologise for. An already-confirmed registration returns unchanged and
     * fires nothing.
     */
    public function confirmPayment(Registration $registration): Registration
    {
        if ($registration->status === RegistrationStatus::Confirmed) {
            return $registration;
        }

        $registration->forceFill([
            'status' => RegistrationStatus::Confirmed,
            'confirmed_at' => Carbon::now(),
            'cancelled_at' => null,
        ])->save();

        RegistrationConfirmed::dispatch($registration);

        return $registration;
    }

    /**
     * Withdraw a registration, releasing its seat and its grant.
     *
     * Never a delete: once payment exists the row is an audit record (doc 03,
     * data lifecycle). Refunds are a payment concern and set `Refunded`
     * separately — cancelling something already refunded would erase that.
     */
    public function cancel(Registration $registration, ?string $reason = null): Registration
    {
        if (! $registration->status->occupiesASeat()) {
            throw RegistrationNotAllowed::cannotCancel();
        }

        $registration->forceFill([
            'status' => RegistrationStatus::Cancelled,
            'cancelled_at' => Carbon::now(),
            'notes' => $this->appendNote($registration->notes, $reason),
        ])->save();

        RegistrationCancelled::dispatch($registration, $reason);

        return $registration;
    }

    /**
     * Whether this organization already holds a live place at this fair.
     *
     * The duplicate rule is "no second NON-CANCELLED registration", which no
     * portable unique index expresses, so it lives here (doc 10, D-1.2-e). The
     * portal also calls this to grey the button out before anyone tries.
     */
    public function alreadyRegistered(Event $event, Organization $organization): bool
    {
        return $event->registrations()
            ->where('organization_id', $organization->getKey())
            ->occupying()
            ->exists();
    }

    /**
     * The shared write path for both entry points.
     *
     * @param  array{rep_name: string, rep_email: string, rep_phone?: string|null}  $contact
     */
    protected function store(
        Event $event,
        Organization $organization,
        ?User $rep,
        ?PaymentMethod $method,
        array $contact,
        ?string $notes = null,
        bool $enforceCapacity = true,
    ): Registration {
        // One transaction around the duplicate check, the capacity check and
        // the insert. Two reps hitting register at the same second would
        // otherwise both read "no existing registration" and both write one.
        return DB::transaction(function () use (
            $event, $organization, $rep, $method, $contact, $notes, $enforceCapacity
        ): Registration {
            if ($this->alreadyRegistered($event, $organization)) {
                throw RegistrationNotAllowed::alreadyRegistered($organization, $event);
            }

            if ($enforceCapacity && $event->isFull()) {
                throw RegistrationNotAllowed::eventIsFull($event);
            }

            // The snapshot (N1). Read from the event and the organization's approved
            // grant — never from anything the caller passed in.
            $grant = $event->approvedGrantFor($organization);
            $priceCents = $event->priceFor($organization);
            $isFree = $priceCents === 0;

            if (! $isFree && ! $method instanceof PaymentMethod) {
                throw RegistrationNotAllowed::paymentMethodRequired();
            }

            $registration = Registration::query()->create([
                'event_id' => $event->getKey(),
                'organization_id' => $organization->getKey(),
                'user_id' => $rep?->getKey(),
                'status' => $isFree ? RegistrationStatus::Confirmed : RegistrationStatus::PendingPayment,
                // A free registration has NO payment method: nothing is ever
                // charged, so recording one would invite a payment row for
                // money that never moved.
                'payment_method' => $isFree ? null : $method,
                'grant_id' => $grant?->getKey(),
                'price_cents' => $priceCents,
                'rep_name' => $contact['rep_name'],
                'rep_email' => $contact['rep_email'],
                'rep_phone' => $contact['rep_phone'] ?? null,
                'show_on_roster' => true,
                'notes' => $notes,
                'confirmed_at' => $isFree ? Carbon::now() : null,
            ]);

            RegistrationCreated::dispatch($registration);

            if ($isFree) {
                // Confirmed on the spot, no gateway, no payment row (R2.4).
                RegistrationConfirmed::dispatch($registration);
            }

            return $registration;
        });
    }

    protected function appendNote(?string $existing, ?string $addition): ?string
    {
        if (blank($addition)) {
            return $existing;
        }

        $stamped = Carbon::now()->toDateString().' — '.$addition;

        return blank($existing) ? $stamped : $existing."\n".$stamped;
    }
}
