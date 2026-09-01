<?php

namespace App\Exceptions;

use App\Models\Event;
use App\Models\Organization;
use RuntimeException;

/**
 * A grant action was refused by a business rule.
 *
 * Same shape and same reasoning as `RegistrationNotAllowed`: the message is
 * the payload, and it is shown to a person, so it is written as copy.
 */
class GrantNotAllowed extends RuntimeException
{
    public static function repIsNotAnActiveMember(Organization $organization): self
    {
        return new self(__(
            'Only an approved representative of :organization can apply for a grant on its behalf.',
            ['organization' => $organization->name],
        ));
    }

    public static function repBelongsToAnotherOrganization(): self
    {
        return new self(__('You can only apply for a grant for the organization your account belongs to.'));
    }

    public static function alreadyApplied(Organization $organization, Event $event): self
    {
        return new self(__(':organization has already applied for a grant for :event.', [
            'organization' => $organization->name,
            'event' => $event->name,
        ]));
    }

    /**
     * Applications close when the fair does. After that there is nothing left
     * to discount.
     */
    public static function eventIsPast(Event $event): self
    {
        return new self(__('Grant applications for :event have closed.', ['event' => $event->name]));
    }

    public static function notPending(): self
    {
        return new self(__('This application has already been decided.'));
    }

    public static function notApproved(): self
    {
        return new self(__('Only an approved grant can be revoked.'));
    }

    /**
     * The one rule that protects an organization rather than the process: once a
     * registration has been priced under a grant, the discount has been given
     * in writing and cannot be taken back.
     */
    public static function grantIsInUse(): self
    {
        return new self(__(
            'This grant has already been used for a registration and can no longer be revoked. '
            .'Cancel the registration first if it really needs to be undone.',
        ));
    }

    public static function benefitIncomplete(): self
    {
        return new self(__('Choose what the grant is worth before approving it.'));
    }

    public static function denialReasonRequired(): self
    {
        return new self(__('Give a reason — it is included in the email the organization receives.'));
    }
}
