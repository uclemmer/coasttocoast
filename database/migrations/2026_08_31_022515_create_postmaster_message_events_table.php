<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postmaster_message_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('message_id')
                ->constrained('postmaster_messages')
                ->cascadeOnDelete();

            $table->string('event')->index();
            $table->timestamp('occurred_at');

            /*
             * The provider's payload, kept verbatim.
             *
             * The timestamps on `postmaster_messages` are a summary — first
             * open, last bounce — and a summary cannot answer "why did this
             * bounce" or "how many times was it opened, and from where". Keep
             * the raw event so the answer is still there when someone asks
             * months later, and so a driver bug can be diagnosed against what
             * actually arrived rather than against what was derived from it.
             *
             * Pruning is by the parent row: `postmaster:prune` deletes
             * messages, and this cascades.
             */
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['message_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postmaster_message_events');
    }
};
