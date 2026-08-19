<?php

namespace App\Console\Commands;

use App\Models\StripeWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Trims the Stripe idempotency ledger (card 7.1).
 *
 * The ledger exists to make a redelivery a no-op, and Stripe stops retrying
 * after about three days. A row older than that has no job left to do, and
 * `payload` is a full JSON event — the table grows without bound otherwise.
 *
 * Ninety days by default rather than three: the ledger is also the answer to
 * "did Stripe ever tell us about this?", and that question comes up weeks
 * later, usually from somebody reconciling a bank statement.
 *
 * Only *processed* rows are removed. One that never got a `processed_at` is a
 * delivery that failed halfway, which is exactly the row worth keeping.
 */
class PruneStripeWebhookEvents extends Command
{
    protected $signature = 'fair:prune-stripe-events {--days=90 : How much history to keep}';

    protected $description = 'Delete processed Stripe webhook ledger rows past their useful life.';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));

        $deleted = StripeWebhookEvent::query()
            ->whereNotNull('processed_at')
            ->where('processed_at', '<', Carbon::now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} processed webhook row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
