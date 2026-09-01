<?php

namespace App\Events;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A claim was refused. The organization is passed separately because by the
 * time this fires the rep no longer points at it — and the email has to name
 * the organization they asked about.
 */
class MembershipDenied
{
    use Dispatchable;

    public function __construct(
        public readonly User $rep,
        public readonly ?Organization $organization = null,
        public readonly ?string $reason = null,
    ) {}
}
