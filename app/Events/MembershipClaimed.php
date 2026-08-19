<?php

namespace App\Events;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A rep signed up against a school that already exists, and is waiting on the
 * coordinator (D9). The alert this drives is the only thing standing between
 * that person and an indefinite "awaiting approval" screen.
 */
class MembershipClaimed
{
    use Dispatchable;

    public function __construct(
        public readonly User $rep,
        public readonly Organization $organization,
    ) {}
}
