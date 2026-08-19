<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — the idempotency ledger for Stripe (doc 04).
 *
 * Stripe retries a webhook until it gets a 2xx, and a retry of
 * checkout.session.completed must not confirm a registration twice or send a
 * second receipt. The unique `stripe_event_id` is what makes the handler
 * idempotent; `processed_at` distinguishes seen-and-in-flight from finished.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type')->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
