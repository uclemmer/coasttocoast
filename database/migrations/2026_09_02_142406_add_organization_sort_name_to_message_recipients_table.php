<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The delivery table's own sort key, frozen like the name it is derived from.
 *
 * The campaign page listed recipients on `organization_name`, so it misfiled
 * every "The University of ..." under T exactly as the roster did before
 * `organizations.sort_name` (doc 10, D-10-a). Same rule, same
 * `Organization::sortName()` — the delivery table and the roster have to agree
 * on where an institution files, and two copies of that rule would drift.
 *
 * **Derived from the snapshot, never joined to the live organization.** These
 * rows record who a campaign was actually sent to, and `MessageRecipient`'s
 * docblock is explicit that a later profile edit must not rewrite them. A join
 * to `organizations.sort_name` would order last year's delivery record by this
 * year's names, and would order nothing at all for a recipient whose
 * organization has since been merged away.
 *
 * **Nullable, unlike `organizations.sort_name`.** `organization_name` is itself
 * nullable — the interest form's organization field is optional, and the
 * generic fallback rows always have one — so the key follows it: no name, no
 * key, and those rows sort first exactly where they sort today. An
 * empty-string default would have said "an organization named nothing".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_recipients', function (Blueprint $table) {
            $table->string('organization_sort_name')->nullable()->after('organization_name')->index();
        });

        DB::table('message_recipients')
            ->whereNotNull('organization_name')
            ->chunkById(500, function ($recipients): void {
                foreach ($recipients as $recipient) {
                    DB::table('message_recipients')
                        ->where('id', $recipient->id)
                        ->update(['organization_sort_name' => Organization::sortName($recipient->organization_name)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('message_recipients', function (Blueprint $table) {
            $table->dropIndex(['organization_sort_name']);
            $table->dropColumn('organization_sort_name');
        });
    }
};
