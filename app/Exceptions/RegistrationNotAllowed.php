<?php

namespace App\Exceptions;

use App\Models\Event;
use App\Models\Organization;
use RuntimeException;

/**
 * A registration was refused by a business rule.
 *
 * One exception with named constructors rather than eight classes: every one
 * of these is "we will not do that, and here is what to tell the person", and
 * the message is the payload. The wizard shows `getMessage()` directly, so the
 * wording here is user-facing copy, not developer shorthand.
 */
class RegistrationNotAllowed extends RuntimeException
{
    public static function repIsNotAnActiveMember(Organization $organization): self
    {
        return new self(__(
            'Only an approved representative of :organization can register it for a fair. '
            .'If your request to join is still pending, the coordinator will be in touch.',
            ['organization' => $organization->name],
        ));
    }

    public static function repBelongsToAnotherOrganization(): self
    {
        return new self(__('You can only register the school your account belongs to.'));
    }

    public static function registrationIsClosed(Event $event): self
    {
        return new self(__('Registration for :event is not open.', ['event' => $event->name]));
    }

    public static function eventIsFull(Event $event): self
    {
        return new self(__(':event is full. Ask the coordinator to be added to the waiting list.', [
            'event' => $event->name,
        ]));
    }

    public static function alreadyRegistered(Organization $organization, Event $event): self
    {
        return new self(__(':organization already has a registration for :event.', [
            'organization' => $organization->name,
            'event' => $event->name,
        ]));
    }

    /**
     * A paid registration with no payment method chosen. Distinct from the
     * free path, where a null method is correct.
     */
    public static function paymentMethodRequired(): self
    {
        return new self(__('Choose how you would like to pay.'));
    }

    public static function cannotCancel(): self
    {
        return new self(__('This registration has already been cancelled or refunded.'));
    }

    public static function notAwaitingACheck(): self
    {
        return new self(__('This registration is not waiting on a check.'));
    }

    public static function notAwaitingPayment(): self
    {
        return new self(__('This registration is not waiting on payment.'));
    }
}
