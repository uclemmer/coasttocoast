<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — a campaign the coordinator composes and sends (doc 07 §3).
 *
 * The audience is stored as an enum case plus filters rather than as a frozen
 * recipient list, because an audience resolves at SEND time (doc 07 §2 rule 6):
 * schedule a note to lapsed organizations and whoever is lapsed when it fires is who
 * receives it. The resolved list lands in `message_recipients`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            // The reference event the audience resolves against. Nullable so a
            // message can be sent with no fair in view; the builder then falls
            // back to the active event.
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();

            $table->string('subject');
            $table->text('email_body')->nullable();
            $table->text('sms_body')->nullable();

            // MessageChannel enum cases.
            $table->json('channels');

            // Audience enum + its composable filters (doc 07 §2).
            $table->string('audience', 40);
            $table->json('audience_filters')->nullable();

            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
