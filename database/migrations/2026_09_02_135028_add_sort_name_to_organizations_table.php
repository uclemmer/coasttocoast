<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The key every organization list is alphabetized on.
 *
 * Ordering by `name` filed eight of the 156 seeded organizations under "The" —
 * splitting the three Alabama campuses across T and U, and putting all four
 * University of Tennessee campuses under T. `Organization::sortName()` carries
 * the full reasoning, including why an organization files under its displayed
 * name (University of Alabama under U) rather than being inverted.
 *
 * Separate from `normalized_name` on purpose. That column looks close enough to
 * reuse, but it exists for duplicate matching, and coupling display order to a
 * dedupe heuristic means the next tweak to that heuristic silently reorders
 * every list on the public site.
 *
 * The backfill calls the model's static rather than restating the rule in SQL:
 * two copies of a derivation drift, and the drift is invisible because both
 * halves keep working. The cost is the usual one for a derived column — if the
 * rule ever changes, this migration does not retroactively change with it and a
 * fresh backfill migration is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Defaulted rather than nullable: it is derived on every save, so
            // there is no such thing as an organization without one, and a raw
            // insert that skips it gets an empty string instead of a failure.
            $table->string('sort_name')->default('')->after('normalized_name')->index();
        });

        DB::table('organizations')->chunkById(200, function ($organizations): void {
            foreach ($organizations as $organization) {
                DB::table('organizations')
                    ->where('id', $organization->id)
                    ->update(['sort_name' => Organization::sortName($organization->name)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['sort_name']);
            $table->dropColumn('sort_name');
        });
    }
};
