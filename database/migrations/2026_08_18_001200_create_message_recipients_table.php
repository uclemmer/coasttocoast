<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — the frozen delivery list for one campaign (doc 07 §2 rule 6, §4).
 *
 * A ULID primary key because the id travels out on the wire as the
 * `X-CTC-Recipient-Id` header and comes back through laravel-core EmailLogged
 * listener; a guessable sequential id in a mail header is an invitation.
 *
 * Every foreign key here is nullable on purpose. Interest-list recipients have
 * no organization and no account; lapsed recipients have no current
 * registration; the generic admissions_email fallback has an organization but
 * no user. The name/email/phone snapshots are what actually gets used, so a
 * later profile edit cannot rewrite who we mailed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_recipients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();

            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            $table->string('organization_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('phone', 20)->nullable();

            // DeliveryStatus enums. The email column is a FALLBACK: when an
            // email log is linked, that row is authoritative (doc 07 §4 rule 3).
            $table->string('email_status', 20)->default('pending');
            $table->string('sms_status', 20)->default('skipped');

            // core_email_logs uses ULID keys. No foreign key constraint: the
            // pruning command deletes logs on a schedule and must not be
            // blocked by, or silently delete, campaign history.
            $table->ulid('email_log_id')->nullable()->index();

            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_recipients');
    }
};
