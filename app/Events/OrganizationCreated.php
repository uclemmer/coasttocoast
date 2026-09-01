<?php

namespace App\Events;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A rep signed up and created an organization that was not in the directory (D9).
 *
 * Carries `$possibleDuplicates` because the coordinator's alert is the place
 * that warning is actually useful: the rep saw it at signup and pressed on
 * anyway, which is allowed — but somebody should look (R2.7).
 *
 * @property-read array<int, string> $possibleDuplicates
 */
class OrganizationCreated
{
    use Dispatchable;

    /**
     * @param  array<int, string>  $possibleDuplicates
     */
    public function __construct(
        public readonly Organization $organization,
        public readonly User $founder,
        public readonly array $possibleDuplicates = [],
    ) {}
}
