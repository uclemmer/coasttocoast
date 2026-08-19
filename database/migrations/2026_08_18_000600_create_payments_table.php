<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — actual money movements against a registration.
 *
 * Separate from `registrations` because one registration can accumulate more
 * than one row: a failed card attempt followed by a successful one, or a
 * success followed by a refund. The registration status is the summary; this
 * table is the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            // PaymentMethod / PaymentStatus enums.
            $table->string('method', 20);
            $table->string('status', 20)->index();

            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('usd');

            // Unique so a replayed checkout.session.completed cannot produce a
            // second payment row even if the idempotency ledger were bypassed.
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->index();

            $table->string('check_number')->nullable();
            $table->date('check_received_on')->nullable();

            // The coordinator who recorded a check; null for anything Stripe did.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
