<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postmaster_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('message_id')->nullable()->index();
            $table->string('mailer')->nullable();
            $table->string('subject')->nullable();

            $table->json('from')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->json('reply_to')->nullable();

            $table->longText('html_body')->nullable();
            $table->longText('text_body')->nullable();

            // Metadata only — never the bytes.
            $table->json('attachments')->nullable();
            $table->json('headers')->nullable();

            $table->string('status')->default('sending')->index();
            $table->text('error')->nullable();
            $table->string('mailable_class')->nullable()->index();

            $table->timestamp('sent_at')->nullable();

            /*
             * The delivery half, and the reason this table is not
             * laravel-core's `core_email_logs`.
             *
             * Core's schema stopped at `sent_at`, so an application running
             * core still had to build a second table to record what the ESP
             * said happened next — projects/uclemmer has exactly that today,
             * two tables that cannot be joined without guessing. `ckbs`, the
             * only implementation here that has run the whole lifecycle in
             * production, resolved it the other way: one row, widened.
             *
             * `provider_message_id` is the join key that makes it work. It is
             * the ESP's own id, not the RFC 5322 Message-ID in `message_id`
             * above — a webhook payload quotes the former and rarely carries
             * the latter, so correlating on `message_id` silently matches
             * nothing.
             */
            $table->string('stream')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamp('first_opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);

            // Set when a log row was produced by resending another one.
            $table->foreignUlid('resent_from_id')->nullable()
                ->constrained('postmaster_messages')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);

            // Webhook ingestion looks a message up by this and nothing else;
            // without the index every bounce is a table scan.
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postmaster_messages');
    }
};
