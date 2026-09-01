<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Events\MembershipApproved;
use App\Events\MembershipClaimed;
use App\Events\MembershipDenied;
use App\Events\MembershipRetired;
use App\Events\OrganizationCreated;
use App\Exceptions\MembershipNotAllowed;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The membership lifecycle (D9, R2.10) and the duplicate merge (R3.3a).
 *
 * These are the operations where an organization and the people who speak for it come
 * apart. Every one of them has to leave the organization's history intact — that is
 * the entire reason the organization, rather than the rep, is the unit that
 * registers.
 *
 * Sends no mail; fires events that card 6.1 listens to (doc 10, D-2.3-a).
 */
class OrganizationService
{
    /**
     * Signup path one: this organization is not in the directory yet (D9).
     *
     * The founder is `active` immediately. There is nobody to approve them —
     * they are the organization's first representative, and making them wait would
     * mean waiting on a coordinator to vouch for an organization only they know
     * about. The coordinator is alerted instead, with the duplicate warning
     * attached, because the rep saw that warning and pressed on and somebody
     * should look.
     *
     * @param  array<string, mixed>  $profile
     */
    public function createWithFounder(array $profile, User $founder): Organization
    {
        return DB::transaction(function () use ($profile, $founder): Organization {
            $duplicates = Organization::query()
                ->matchingName((string) ($profile['name'] ?? ''))
                ->pluck('name')
                ->all();

            $organization = Organization::query()->create($profile + [
                'created_by' => $founder->getKey(),
            ]);

            $founder->forceFill([
                'organization_id' => $organization->getKey(),
                'membership_status' => MembershipStatus::Active,
                'membership_approved_at' => Carbon::now(),
            ])->save();

            OrganizationCreated::dispatch($organization, $founder, $duplicates);

            return $organization;
        });
    }

    /**
     * Signup path two: the organization is already in the directory (D9).
     *
     * The rep is `pending` until a coordinator approves. This asymmetry with
     * `createWithFounder()` is the whole point of the two paths: anyone can
     * claim to represent Vanderbilt, and the organization's registration history,
     * grants and roster entry are on the other side of that claim.
     */
    public function claim(Organization $organization, User $rep): User
    {
        $rep->forceFill([
            'organization_id' => $organization->getKey(),
            'membership_status' => MembershipStatus::Pending,
            'membership_approved_at' => null,
            'retired_at' => null,
            'retired_by' => null,
        ])->save();

        MembershipClaimed::dispatch($rep, $organization);

        return $rep;
    }

    /**
     * A coordinator approving a rep's claim on an existing organization.
     */
    public function approveClaim(User $rep, User $coordinator): User
    {
        if (! $rep->isPendingApproval()) {
            throw MembershipNotAllowed::notPending();
        }

        $rep->forceFill([
            'membership_status' => MembershipStatus::Active,
            'membership_approved_at' => Carbon::now(),
            'retired_at' => null,
            'retired_by' => null,
        ])->save();

        MembershipApproved::dispatch($rep);

        return $rep;
    }

    /**
     * A coordinator refusing a claim.
     *
     * The account survives with no organization at all rather than being
     * deleted or left `pending` forever. Someone who claimed the wrong organization
     * — a typo, a similar name — should be able to sign up again for the right
     * one, which a lingering pending membership would block.
     */
    public function denyClaim(User $rep, User $coordinator, ?string $reason = null): User
    {
        if (! $rep->isPendingApproval()) {
            throw MembershipNotAllowed::notPending();
        }

        $organization = $rep->organization;

        $rep->forceFill([
            'organization_id' => null,
            'membership_status' => null,
            'membership_approved_at' => null,
        ])->save();

        MembershipDenied::dispatch($rep, $organization, $reason);

        return $rep;
    }

    /**
     * Retire a rep — by their own hand or the coordinator's.
     *
     * They keep the account, the login and the visible history; they lose
     * every right to act for the organization, and campaigns stop mailing them
     * (doc 07 §2 rule 1). The organization is untouched: its registrations,
     * grants and roster entries were never the rep's to take with them.
     */
    public function retire(User $rep, User $actor): User
    {
        if ($rep->organization_id === null || $rep->isRetired()) {
            throw MembershipNotAllowed::notAMember();
        }

        $rep->forceFill([
            'membership_status' => MembershipStatus::Retired,
            'retired_at' => Carbon::now(),
            'retired_by' => $actor->getKey(),
        ])->save();

        MembershipRetired::dispatch($rep, $actor->is($rep));

        return $rep;
    }

    /**
     * Bring a retired rep back — the person who left and came back, which
     * happens more than the schema suggests.
     */
    public function reinstate(User $rep, User $coordinator): User
    {
        if (! $rep->isRetired()) {
            throw MembershipNotAllowed::notRetired();
        }

        $rep->forceFill([
            'membership_status' => MembershipStatus::Active,
            'membership_approved_at' => Carbon::now(),
            'retired_at' => null,
            'retired_by' => null,
        ])->save();

        MembershipApproved::dispatch($rep);

        return $rep;
    }

    /**
     * Fold a duplicate organization into the one being kept (R3.3a).
     *
     * Everything that points at the duplicate is repointed — reps,
     * registrations, grants — and only then is the husk deleted. Order
     * matters: the foreign keys cascade, so deleting first would take the
     * registrations and grants with it, which is precisely the history the
     * merge exists to preserve.
     *
     * Profile fields on the survivor are filled in from the duplicate only
     * where the survivor has none. A merge must never overwrite information
     * somebody deliberately entered.
     *
     * The one thing this does NOT resolve is two live registrations for the
     * same fair, which the merge can create. That is reported back rather than
     * silently fixed — deciding which of two registrations an organization keeps is a
     * judgement about money, not a data-cleanup step.
     *
     * @return array<int, Registration> registrations that now collide on a fair
     */
    public function merge(Organization $duplicate, Organization $keep): array
    {
        if ($duplicate->is($keep)) {
            throw MembershipNotAllowed::cannotMergeIntoItself();
        }

        return DB::transaction(function () use ($duplicate, $keep): array {
            $fairsAlreadyHeld = $keep->registrations()->occupying()->pluck('event_id');

            $collisions = $duplicate->registrations()->occupying()
                ->whereIn('event_id', $fairsAlreadyHeld)
                ->get()
                ->all();

            $duplicate->users()->update(['organization_id' => $keep->getKey()]);
            $duplicate->registrations()->update(['organization_id' => $keep->getKey()]);
            $duplicate->grants()->update(['organization_id' => $keep->getKey()]);

            $this->fillGapsFrom($duplicate, $keep);

            // Reload so the delete does not cascade over rows we just moved.
            $duplicate->refresh()->delete();

            return $collisions;
        });
    }

    /**
     * Copy profile fields from the duplicate into the survivor, but only where
     * the survivor has nothing.
     */
    protected function fillGapsFrom(Organization $duplicate, Organization $keep): void
    {
        $fields = [
            'website', 'logo_path', 'admissions_office', 'admissions_email', 'admissions_phone',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        ];

        $gaps = [];

        foreach ($fields as $field) {
            if (blank($keep->{$field}) && filled($duplicate->{$field})) {
                $gaps[$field] = $duplicate->{$field};
            }
        }

        if ($gaps !== []) {
            $keep->forceFill($gaps)->save();
        }
    }
}
