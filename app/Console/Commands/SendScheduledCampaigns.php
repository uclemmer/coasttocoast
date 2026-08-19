<?php

namespace App\Console\Commands;

use App\Jobs\SendEventBroadcast;
use App\Models\Message;
use Illuminate\Console\Command;

/**
 * Fires campaigns whose scheduled moment has arrived (doc 07 §3).
 *
 * Runs every minute from the scheduler. It dispatches rather than sends —
 * `SendEventBroadcast` resolves the audience and does the fan-out, and it
 * stamps `sent_at` before it starts, so this command cannot pick the same
 * message up twice even if two workers run it at once.
 */
class SendScheduledCampaigns extends Command
{
    protected $signature = 'fair:send-scheduled-campaigns';

    protected $description = 'Dispatch any campaign whose scheduled send time has passed.';

    public function handle(): int
    {
        $due = Message::query()->dueToSend()->get();

        foreach ($due as $message) {
            SendEventBroadcast::dispatch($message);

            $this->info("Dispatched: {$message->subject}");
        }

        // Said out loud rather than left silent: a coordinator asking "did my
        // scheduled note go out?" is answered from the log.
        $this->info($due->isEmpty() ? 'No campaigns due.' : $due->count().' campaign(s) dispatched.');

        return self::SUCCESS;
    }
}
