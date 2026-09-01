<?php

namespace App\Services;

use App\Enums\Audience;
use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventInterest;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\Audiences\RecipientDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Who a campaign goes to (doc 07 §2).
 *
 * The design in one sentence: **audiences qualify organizations and deliver to
 * people.** An organization earns its place on a list through its registration
 * history; the recipients are that organization's *active* representatives. That
 * indirection is what makes the cross-year lists work at all — reps come and
 * go, and "last year's organizations" has to keep meaning something after the person
 * who registered them has left.
 *
 * Four rules follow from it, and every one is a test:
 *
 *  1. **Pending and retired reps are never emailed** (R2.10). An organization with no
 *     active rep falls back to one `generic` recipient at its
 *     `admissions_email`; with neither, it is dropped and the drop is logged,
 *     because an organization vanishing silently from a win-back list is how it stops
 *     being invited.
 *  2. **Dedupe by account, then by address.** A rep active across three past
 *     years qualifies three times and receives one email.
 *  3. **Cancelled and refunded registrations never qualify anybody.** They
 *     didn't attend.
 *  4. **"Previous" and "last" mean published events by start date** — the same
 *     `previousPublished()` scope the Last Year page uses, so the site and the
 *     mailing list cannot disagree about which fair was last.
 *
 * Resolution happens at SEND time, not compose time (rule 6): schedule a note
 * to lapsed organizations and whoever is lapsed when it fires is who gets it.
 */
class AudienceBuilder
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, RecipientDto>
     */
    public function resolve(Audience $audience, ?Event $reference = null, array $filters = []): Collection
    {
        $reference ??= Event::active();

        $recipients = $audience->isEmailOnly()
            ? $this->fromInterestList($reference)
            : $this->fromOrganizations($this->qualifyingOrganizationIds($audience, $reference), $reference, $filters);

        return $this->applyFilters($recipients, $filters);
    }

    /**
     * How many people a send would reach — the preview count the composer
     * shows before anyone presses send (doc 07 §3).
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(Audience $audience, ?Event $reference = null, array $filters = []): int
    {
        return $this->resolve($audience, $reference, $filters)->count();
    }

    /**
     * The organizations an audience selects.
     *
     * @return Collection<int, int>
     */
    protected function qualifyingOrganizationIds(Audience $audience, ?Event $reference): Collection
    {
        if (! $reference instanceof Event) {
            return collect();
        }

        return match ($audience) {
            Audience::ThisEventConfirmed => $this->organizationsOn(
                $reference, [RegistrationStatus::Confirmed],
            ),
            Audience::ThisEventPendingCheck => $this->organizationsOn(
                $reference, [RegistrationStatus::PendingPayment], PaymentMethod::Check,
            ),
            Audience::ThisEventAll => $this->registeredForThisEvent($reference),

            Audience::LastEvent => $this->organizationsOnPreviousEvent($reference),
            Audience::LapsedLastEvent => $this->organizationsOnPreviousEvent($reference)
                ->diff($this->registeredForThisEvent($reference)),

            Audience::AnyPreviousEvent => $this->organizationsOnAnyPastEvent($reference),
            Audience::LapsedAnyPrevious => $this->organizationsOnAnyPastEvent($reference)
                ->diff($this->registeredForThisEvent($reference)),

            // Handled before this method is reached.
            Audience::InterestList => collect(),
        };
    }

    /**
     * @param  array<int, RegistrationStatus>  $statuses
     * @return Collection<int, int>
     */
    protected function organizationsOn(Event $event, array $statuses, ?PaymentMethod $method = null): Collection
    {
        return Registration::query()
            ->where('event_id', $event->getKey())
            ->whereIn('status', $statuses)
            ->when($method instanceof PaymentMethod, fn ($query) => $query->where('payment_method', $method))
            ->pluck('organization_id')
            ->unique()
            ->values();
    }

    /**
     * Everyone with a live registration for the reference fair — the set the
     * lapsed audiences subtract.
     *
     * @return Collection<int, int>
     */
    protected function registeredForThisEvent(Event $event): Collection
    {
        return $this->organizationsOn($event, RegistrationStatus::occupying());
    }

    /**
     * @return Collection<int, int>
     */
    protected function organizationsOnPreviousEvent(Event $reference): Collection
    {
        $previous = Event::query()->previousPublished($reference->starts_at)->first();

        return $previous instanceof Event
            ? $this->organizationsOn($previous, RegistrationStatus::occupying())
            : collect();
    }

    /**
     * @return Collection<int, int>
     */
    protected function organizationsOnAnyPastEvent(Event $reference): Collection
    {
        $pastEventIds = Event::query()
            ->previousPublished($reference->starts_at)
            ->pluck('id');

        return Registration::query()
            ->whereIn('event_id', $pastEventIds)
            ->whereIn('status', RegistrationStatus::occupying())
            ->pluck('organization_id')
            ->unique()
            ->values();
    }

    /**
     * Organizations to people (rule 1).
     *
     * @param  Collection<int, int>  $organizationIds
     * @param  array<string, mixed>  $filters
     * @return Collection<int, RecipientDto>
     */
    protected function fromOrganizations(
        Collection $organizationIds,
        ?Event $reference,
        array $filters,
    ): Collection {
        if ($organizationIds->isEmpty()) {
            return collect();
        }

        $organizations = Organization::query()
            ->whereIn('id', $organizationIds)
            ->with('activeReps')
            ->get();

        // One query for the whole set rather than one per organization, so that the
        // registration id can be attached without an N+1.
        $registrationIds = $reference instanceof Event
            ? Registration::query()
                ->where('event_id', $reference->getKey())
                ->whereIn('status', RegistrationStatus::occupying())
                ->pluck('id', 'organization_id')
            : collect();

        $recipients = collect();
        $skipGeneric = (bool) ($filters['skipGenericFallback'] ?? false);

        foreach ($organizations as $organization) {
            $registrationId = $registrationIds[$organization->getKey()] ?? null;

            if ($organization->activeReps->isNotEmpty()) {
                foreach ($organization->activeReps as $rep) {
                    $recipients->push($this->fromRep($rep, $organization, $registrationId));
                }

                continue;
            }

            if ($skipGeneric) {
                continue;
            }

            $recipients->push($this->generic($organization, $registrationId));
        }

        return $recipients->filter()->values();
    }

    protected function fromRep(User $rep, Organization $organization, ?int $registrationId): RecipientDto
    {
        // Rule 3: the freshest contact details win. The rep's account, not a
        // snapshot on some registration from three years ago.
        return new RecipientDto(
            email: $rep->email,
            name: $rep->name,
            userId: $rep->getKey(),
            organizationId: $organization->getKey(),
            organizationName: $organization->name,
            registrationId: $registrationId,
            phone: $rep->phone,
            smsOptIn: (bool) $rep->sms_opt_in,
        );
    }

    /**
     * The fallback for an organization with nobody active, or null when it has no
     * general address either.
     */
    protected function generic(Organization $organization, ?int $registrationId): ?RecipientDto
    {
        if (blank($organization->admissions_email)) {
            // Logged, never silent. An organization dropping off a win-back list
            // without a trace is how it stops being invited (doc 07: "no
            // silent caps").
            Log::info('Campaign audience dropped an organization with no active reps and no admissions email.', [
                'organization_id' => $organization->getKey(),
                'organization' => $organization->name,
            ]);

            return null;
        }

        return new RecipientDto(
            email: $organization->admissions_email,
            name: null,
            userId: null,
            organizationId: $organization->getKey(),
            organizationName: $organization->name,
            registrationId: $registrationId,
            generic: true,
        );
    }

    /**
     * The interest list: addresses with no organization and no account behind them.
     *
     * @return Collection<int, RecipientDto>
     */
    protected function fromInterestList(?Event $reference): Collection
    {
        if (! $reference instanceof Event) {
            return collect();
        }

        return EventInterest::query()
            ->where('event_id', $reference->getKey())
            ->get()
            ->map(fn (EventInterest $interest): RecipientDto => new RecipientDto(
                email: $interest->email,
                organizationName: $interest->organization_name,
            ));
    }

    /**
     * Dedupe (rule 2), then the composable filters from doc 07 §2.
     *
     * @param  Collection<int, RecipientDto>  $recipients
     * @param  array<string, mixed>  $filters
     * @return Collection<int, RecipientDto>
     */
    protected function applyFilters(Collection $recipients, array $filters): Collection
    {
        $excluded = collect($filters['excludeEmails'] ?? [])
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->all();

        return $recipients
            ->unique(fn (RecipientDto $recipient): string => $recipient->dedupeKey())
            ->when(
                (bool) ($filters['smsOptedInOnly'] ?? false),
                fn (Collection $all): Collection => $all->filter(
                    fn (RecipientDto $recipient): bool => $recipient->canReceiveSms(),
                ),
            )
            ->reject(fn (RecipientDto $recipient): bool => in_array(
                mb_strtolower($recipient->email), $excluded, strict: true,
            ))
            ->values();
    }
}
