<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_email_logs', function (Blueprint $table): void {
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

            // Set when a log row was produced by resending another one.
            $table->foreignUlid('resent_from_id')->nullable()
                ->constrained('core_email_logs')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_email_logs');
    }
};
