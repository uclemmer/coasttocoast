<?php

namespace App\Services;

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Events\GrantApplied;
use App\Events\GrantApproved;
use App\Events\GrantDenied;
use App\Events\GrantRevoked;
use App\Events\GrantWithdrawn;
use App\Exceptions\GrantNotAllowed;
use App\Models\Event;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applications for free or discounted registration (D10).
 *
 * The asymmetry here is the design. An organization **asks**; the coordinator decides
 * both whether to approve and what the grant is worth — the applicant never
 * names a figure, which is why `apply()` takes only a justification and
 * `approve()` takes the benefit.
 *
 * Nothing is ever deleted. A denial, a revocation and a withdrawal are all
 * records (doc 03, data lifecycle), and only `GrantStatus::Approved` is
 * visible to `Event::priceFor()`.
 *
 * Sends no mail; fires events that card 6.1 listens to.
 */
class GrantService
{
    /**
     * A representative applying on behalf of their organization.
     */
    public function apply(Event $event, Organization $organization, User $rep, string $justification): Grant
    {
        if ($rep->organization_id !== $organization->getKey()) {
            throw GrantNotAllowed::repBelongsToAnotherOrganization();
        }

        if (! $rep->actsForOrganization()) {
            throw GrantNotAllowed::repIsNotAnActiveMember($organization);
        }

        // Open while the fair is still ahead of us — including before
        // registration opens, so an organization can line its funding up first, which
        // is the whole point of applying. Closed once the fair has happened.
        if ($event->starts_at->isPast()) {
            throw GrantNotAllowed::eventIsPast($event);
        }

        return DB::transaction(function () use ($event, $organization, $rep, $justification): Grant {
            if ($this->hasLiveApplication($event, $organization)) {
                throw GrantNotAllowed::alreadyApplied($organization, $event);
            }

            $grant = Grant::query()->create([
                'organization_id' => $organization->getKey(),
                'event_id' => $event->getKey(),
                'requested_by' => $rep->getKey(),
                'justification' => $justification,
                'status' => GrantStatus::Pending,
            ]);

            GrantApplied::dispatch($grant);

            return $grant;
        });
    }

    /**
     * The coordinator approving an application and setting what it is worth.
     *
     * The benefit parameters are validated here rather than trusted from a
     * form, because a `custom_price` grant with no price or a `percent_off`
     * grant with no percentage would silently fall through
     * `Event::priceFor()` to list price — the organization would be told it had a
     * grant and then charged in full.
     */
    public function approve(
        Grant $grant,
        User $coordinator,
        GrantBenefit $benefit,
        ?int $customPriceCents = null,
        ?int $percentOff = null,
    ): Grant {
        if ($grant->status !== GrantStatus::Pending) {
            throw GrantNotAllowed::notPending();
        }

        $this->assertBenefitIsComplete($benefit, $customPriceCents, $percentOff);

        $grant->forceFill([
            'status' => GrantStatus::Approved,
            'benefit_type' => $benefit,
            'custom_price_cents' => $benefit === GrantBenefit::CustomPrice ? $customPriceCents : null,
            'percent_off' => $benefit === GrantBenefit::PercentOff ? $percentOff : null,
            'decided_by' => $coordinator->getKey(),
            'decided_at' => Carbon::now(),
            'denial_reason' => null,
        ])->save();

        GrantApproved::dispatch($grant);

        return $grant;
    }

    /**
     * Decline an application. The reason is required because it goes into the
     * email the organization receives, and "denied" with no explanation is how you
     * lose an organization for good.
     */
    public function deny(Grant $grant, User $coordinator, string $reason): Grant
    {
        if ($grant->status !== GrantStatus::Pending) {
            throw GrantNotAllowed::notPending();
        }

        if (blank($reason)) {
            throw GrantNotAllowed::denialReasonRequired();
        }

        $grant->forceFill([
            'status' => GrantStatus::Denied,
            'decided_by' => $coordinator->getKey(),
            'decided_at' => Carbon::now(),
            'denial_reason' => $reason,
        ])->save();

        GrantDenied::dispatch($grant);

        return $grant;
    }

    /**
     * Take back an approved grant — only while nothing has used it.
     *
     * The benefit columns are deliberately left populated: a revoked grant
     * must still show what it was worth. Pricing ignores it anyway, because
     * `priceFor()` reads status, not benefit.
     */
    public function revoke(Grant $grant, User $coordinator, ?string $reason = null): Grant
    {
        if ($grant->status !== GrantStatus::Approved) {
            throw GrantNotAllowed::notApproved();
        }

        if ($grant->isUsed()) {
            throw GrantNotAllowed::grantIsInUse();
        }

        $grant->forceFill([
            'status' => GrantStatus::Revoked,
            'decided_by' => $coordinator->getKey(),
            'decided_at' => Carbon::now(),
            'denial_reason' => $reason ?? $grant->denial_reason,
        ])->save();

        GrantRevoked::dispatch($grant);

        return $grant;
    }

    /**
     * An organization changing its mind while the application is still pending.
     *
     * The only status that frees the one-per-fair slot, so an organization that
     * withdraws can apply again with a better case. A denial cannot be
     * withdrawn — that decision is the coordinator's and it stands.
     */
    public function withdraw(Grant $grant, User $rep): Grant
    {
        if ($grant->status !== GrantStatus::Pending) {
            throw GrantNotAllowed::notPending();
        }

        if ($rep->organization_id !== $grant->organization_id || ! $rep->actsForOrganization()) {
            throw GrantNotAllowed::repBelongsToAnotherOrganization();
        }

        $grant->forceFill(['status' => GrantStatus::Withdrawn])->save();

        GrantWithdrawn::dispatch($grant);

        return $grant;
    }

    /**
     * Whether this organization already holds an application for this fair that
     * blocks another. Everything but `Withdrawn` blocks.
     */
    public function hasLiveApplication(Event $event, Organization $organization): bool
    {
        return $event->grants()
            ->where('organization_id', $organization->getKey())
            ->blockingReapplication()
            ->exists();
    }

    /**
     * The application this organization currently holds for this fair, if any —
     * what the portal's status timeline renders (card 3.5).
     */
    public function currentApplication(Event $event, Organization $organization): ?Grant
    {
        return $event->grants()
            ->where('organization_id', $organization->getKey())
            ->blockingReapplication()
            ->latest('id')
            ->first();
    }

    protected function assertBenefitIsComplete(
        GrantBenefit $benefit,
        ?int $customPriceCents,
        ?int $percentOff,
    ): void {
        $complete = match ($benefit) {
            GrantBenefit::Free => true,
            GrantBenefit::CustomPrice => $customPriceCents !== null && $customPriceCents >= 0,
            GrantBenefit::PercentOff => $percentOff !== null && $percentOff >= 1 && $percentOff <= 100,
        };

        if (! $complete) {
            throw GrantNotAllowed::benefitIncomplete();
        }
    }
}
