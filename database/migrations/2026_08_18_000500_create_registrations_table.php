<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — one organization's place at one fair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // The rep who registered. Null for a coordinator's manual entry and
            // for imported history (card 6.6) — hence the contact snapshot below.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // RegistrationStatus enum.
            $table->string('status', 20)->index();

            // PaymentMethod enum. NULL is meaningful: a registration made free
            // by a 100% grant never chooses a method because no payment happens.
            $table->string('payment_method', 20)->nullable();

            $table->foreignId('grant_id')->nullable()->constrained()->nullOnDelete();

            // The snapshot of what this organization was actually charged (N1).
            // Written once from Event::priceFor() and never recomputed — if the
            // event price or the grant changes afterwards, what was charged does not.
            $table->unsignedInteger('price_cents');

            // Contact for this fair, which is not always the account holder
            // details and must survive the account being retired.
            $table->string('rep_name');
            $table->string('rep_email');
            $table->string('rep_phone', 20)->nullable();

            $table->boolean('show_on_roster')->default(true);
            $table->text('notes')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // The duplicate rule (R2.7) is no second non-cancelled registration
            // for the same organization and fair, which no portable unique index can
            // say. RegistrationService::create() enforces it; this index is what
            // makes that check, the roster query and the capacity count cheap.
            $table->index(['event_id', 'organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
