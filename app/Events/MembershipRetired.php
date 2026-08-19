<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A rep has stepped down. `$selfService` distinguishes the two cases, which
 * want different emails: someone who retired themselves needs a confirmation,
 * someone the coordinator retired needs an explanation.
 */
class MembershipRetired
{
    use Dispatchable;

    public function __construct(
        public readonly User $rep,
        public readonly bool $selfService = false,
    ) {}
}
