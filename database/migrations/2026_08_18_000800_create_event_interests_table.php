<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — the notify-me list on a closed event page (R2.7).
 *
 * Deliberately not tied to a user or an organization: someone who finds the
 * site after registration closes has no account, and demanding one is how you
 * lose the lead. `notified_at` is stamped by the announcement action (card 6.5)
 * so re-running it is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('organization_name')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_interests');
    }
};
