<?php

namespace App\Events;

use App\Models\Grant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * See App\Services\GrantService. Card 6.1 hangs the decision notifications off
 * these; the service itself sends no mail.
 */
class GrantWithdrawn
{
    use Dispatchable;

    public function __construct(public readonly Grant $grant) {}
}
