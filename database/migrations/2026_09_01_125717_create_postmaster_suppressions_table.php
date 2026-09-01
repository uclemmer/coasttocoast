<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postmaster_suppressions', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            /*
             * One row per address, stored and matched lowercased.
             *
             * Unique because the list is a set: an address is either refused or
             * it is not, and a second row would make `isSuppressed()` depend on
             * which one the query happened to find. Ingestion upserts against
             * this constraint, which is also what makes a replayed provider
             * webhook harmless.
             */
            $table->string('email')->unique();

            $table->string('reason')->index();
            $table->string('source')->default('automatic');

            // The provider's own description, or an admin's note. Free text on
            // purpose -- it is for a human reading the screen, not for logic.
            $table->text('detail')->nullable();

            /*
             * Who did it, when a person did. A plain nullable string, not a
             * foreign key: this package does not own a user model and has to
             * install into an application with no `users` table at all.
             */
            $table->string('suppressed_by')->nullable();

            /*
             * Only soft bounces get one. Everything else is null, meaning "until
             * something lifts it" -- see SuppressionReason::isTemporary().
             *
             * Indexed with `email` because the hot query is "is this address
             * refused right now", which reads both columns and runs on every
             * message the application sends.
             */
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['email', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postmaster_suppressions');
    }
};
