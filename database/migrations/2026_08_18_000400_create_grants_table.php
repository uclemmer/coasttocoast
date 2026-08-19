<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — an organization's application for free or discounted
 * registration at one event (D10).
 *
 * Created before `registrations` because a registration may reference the
 * approved grant it was priced under.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // The active rep who applied. Retained even if they later retire —
            // the application is a historical fact.
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('justification');

            // GrantStatus enum.
            $table->string('status', 20)->index();

            // GrantBenefit enum plus its parameters — all null until approval,
            // since only the coordinator decides what a grant is worth.
            $table->string('benefit_type', 20)->nullable();
            $table->unsignedInteger('custom_price_cents')->nullable();
            $table->unsignedTinyInteger('percent_off')->nullable();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('denial_reason')->nullable();

            $table->timestamps();

            // One live application per school per fair is enforced in
            // GrantService, not here: a withdrawn application must be allowed to
            // sit alongside its replacement, which a database unique index
            // cannot express portably across SQLite, MySQL and Postgres.
            $table->index(['organization_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grants');
    }
};
