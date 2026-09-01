<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — organization membership on users (D9, R2.10).
 *
 * All four columns are nullable because coordinators have no organization at
 * all: a null `membership_status` is not "unknown", it is "this person is not
 * a representative". Reps get `pending` when they claim an existing organization and
 * `active` when they create a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('sms_opt_in')
                ->constrained('organizations')->nullOnDelete();

            // MembershipStatus enum, stored as a string (doc 02 convention 4).
            $table->string('membership_status', 20)->nullable()->after('organization_id');
            $table->timestamp('membership_approved_at')->nullable()->after('membership_status');
            $table->timestamp('retired_at')->nullable()->after('membership_approved_at');

            // Self-retire and coordinator-retire are the same transition with a
            // different actor, and the difference matters when a rep disputes it.
            $table->foreignId('retired_by')->nullable()->after('retired_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retired_by');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['membership_status', 'membership_approved_at', 'retired_at']);
        });
    }
};
