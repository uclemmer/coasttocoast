<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A rep's claim on an organization was approved, or a retired rep was reinstated.
 * Either way they can now act for the organization. Card 6.1 mails them.
 */
class MembershipApproved
{
    use Dispatchable;

    public function __construct(public readonly User $rep) {}
}
