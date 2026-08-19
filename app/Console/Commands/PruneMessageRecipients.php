<?php

namespace App\Console\Commands;

use App\Models\MessageRecipient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The 24-month privacy promise, applied to campaign delivery rows (N3, card 7.1).
 *
 * `message_recipients` holds a name, an email address and sometimes a phone
 * number for everybody a campaign ever reached. laravel-core prunes its own
 * email logs and contact submissions on a schedule; this is our half of the
 * same promise.
 *
 * The `messages` rows survive — subject, audience, when it went out. What is
 * removed is the personal data, not the record that a campaign happened.
 */
class PruneMessageRecipients extends Command
{
    protected $signature = 'fair:prune-message-recipients {--months=24 : How much history to keep}';

    protected $description = 'Delete campaign recipient rows older than the retention promise.';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = Carbon::now()->subMonths($months);

        $deleted = MessageRecipient::query()->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} recipient row(s) older than {$months} months.");

        return self::SUCCESS;
    }
}
