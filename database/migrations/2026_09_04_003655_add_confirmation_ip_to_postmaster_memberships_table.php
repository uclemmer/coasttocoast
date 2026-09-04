<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The other half of the consent record.
 *
 * A separate migration rather than an edit to the create stub, because that
 * stub has already been published and run in three hosts. Editing it would give
 * new installs the column and leave existing ones without, silently — the same
 * reason `add_ingestion_columns_...` exists beside its own create stub.
 *
 * ## Why the confirmation IP is worth a column of its own
 *
 * `consent_ip` records where the FORM was submitted from, and anyone can submit
 * a form for anyone — which is the entire reason double opt-in exists. This
 * records where the CONFIRMATION came from, and that is evidence somebody
 * reading that mailbox acted. When a spam complaint is disputed, it is the half
 * a mailbox provider actually wants.
 *
 * ## Nullable, and null is meaningful rather than missing
 *
 * A single opt-in list confirms nothing. A confirmation made from a console
 * command or a seeder has no request behind it. And a row confirmed before this
 * column existed has no such record at all. Backfilling any of those — to the
 * signup IP, say, which is the tempting one — would put a fact in the consent
 * record that nobody can stand behind, in the one table whose whole purpose is
 * to be produced as evidence.
 *
 * Added 2026-09-03, when `projects/embergrad` adopted this package. Its own list
 * had recorded both a signup IP and a confirm IP since it was built, and its
 * migration said in writing that the pair *is* the consent record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('postmaster_memberships', 'confirmation_ip')) {
            return;
        }

        Schema::table('postmaster_memberships', function (Blueprint $table): void {
            // 45 is INET6_ADDRSTRLEN, matching `consent_ip`.
            $table->string('confirmation_ip', 45)->nullable()->after('confirmation_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('postmaster_memberships', function (Blueprint $table): void {
            $table->dropColumn('confirmation_ip');
        });
    }
};
