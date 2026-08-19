<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A membership or merge action was refused. Same shape and reasoning as
 * `RegistrationNotAllowed`: the message is user-facing copy.
 */
class MembershipNotAllowed extends RuntimeException
{
    public static function notPending(): self
    {
        return new self(__('This request has already been decided.'));
    }

    public static function notAMember(): self
    {
        return new self(__('This person is not currently a representative of a school.'));
    }

    public static function notRetired(): self
    {
        return new self(__('This representative has not retired.'));
    }

    public static function cannotMergeIntoItself(): self
    {
        return new self(__('Choose a different school to merge into.'));
    }
}
