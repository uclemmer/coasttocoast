<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The interest list's sort key — added now that something reads it.
 *
 * D-10-b considered this column and declined it: nothing listed the table, so
 * it would have been indexed, hooked and read by nobody. The condition it
 * recorded for changing that answer was "a `/staff` screen for the interest
 * list", and that screen is the change this ships with (doc 10, D-10-c).
 *
 * Same `Organization::sortName()` as the roster and the delivery table, so a
 * coordinator moving between the three screens meets one alphabet.
 *
 * Nullable, like the column it derives from: the public interest form's
 * organization field is optional, and a signup that skipped it has nothing to
 * file under. Those rows sort first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_interests', function (Blueprint $table) {
            $table->string('organization_sort_name')->nullable()->after('organization_name')->index();
        });

        DB::table('event_interests')
            ->whereNotNull('organization_name')
            ->chunkById(500, function ($interests): void {
                foreach ($interests as $interest) {
                    DB::table('event_interests')
                        ->where('id', $interest->id)
                        ->update(['organization_sort_name' => Organization::sortName($interest->organization_name)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('event_interests', function (Blueprint $table) {
            $table->dropIndex(['organization_sort_name']);
            $table->dropColumn('organization_sort_name');
        });
    }
};
