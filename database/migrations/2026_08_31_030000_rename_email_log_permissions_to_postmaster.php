<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carry the email-log permissions over to the names laravel-postmaster uses.
 *
 * `email-log.view` / `email-log.manage` were laravel-core's, and left with the
 * feature in core `0.5.0`. The package registers `postmaster.view` /
 * `postmaster.manage` through `core.permission_providers` instead.
 *
 * **This is a RENAME, not a delete-and-recreate, and that is the whole point.**
 * `core_permission_role` links roles to permissions by id, so renaming the row
 * carries every existing grant with it. Creating new rows would leave every
 * role holding the old permission and none holding the new one — which is
 * exactly the state this migration was written to fix, and it fails silently:
 * the admin screen is registered, the route resolves, and the navigation entry
 * simply does not appear for anybody.
 *
 * Found by opening `/admin` after the upgrade and noticing the missing link.
 * The suite could not catch it because it seeds permissions fresh on every run,
 * so it never sees a database that predates the rename.
 *
 * `core:sync-permissions` is not a substitute. It is additive — it creates what
 * the registry declares and leaves what it does not, so on its own it would add
 * the two new permissions, grant them to nobody, and leave the two dead ones in
 * place.
 */
return new class extends Migration
{
    /**
     * Old name => new name.
     *
     * @var array<string, string>
     */
    protected array $renames = [
        'email-log.view' => 'postmaster.view',
        'email-log.manage' => 'postmaster.manage',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('core_permissions')) {
            return;
        }

        foreach ($this->renames as $old => $new) {
            // A host that already ran core:sync-permissions has the new row
            // and the old one side by side. Renaming onto an existing name
            // would violate the unique index, so drop the empty newcomer and
            // rename the one carrying the grants.
            $newId = DB::table('core_permissions')->where('name', $new)->value('id');
            $oldId = DB::table('core_permissions')->where('name', $old)->value('id');

            if ($oldId === null) {
                continue;
            }

            if ($newId !== null) {
                DB::table('core_permission_role')->where('permission_id', $newId)->delete();
                DB::table('core_permissions')->where('id', $newId)->delete();
            }

            DB::table('core_permissions')->where('id', $oldId)->update([
                'name' => $new,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('core_permissions')) {
            return;
        }

        foreach ($this->renames as $old => $new) {
            DB::table('core_permissions')
                ->where('name', $new)
                ->update(['name' => $old, 'updated_at' => now()]);
        }
    }
};
