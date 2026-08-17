<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks user rows this package created on somebody's behalf rather than by
 * their own signup — today that means the contact form.
 *
 * The column is on the HOST's users table, like the two-factor columns, because
 * the question "is this a real user or a shell we made?" has to be answerable
 * from any query the host writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (new (config('core.auth.user_model', 'App\\Models\\User')))->getTable();

        Schema::table($table, function (Blueprint $blueprint): void {
            /*
             * Null means "a real person made this account". Non-null is the
             * moment we created it for them, unasked — which is exactly what
             * makes it prunable, excludable, and claimable.
             */
            $blueprint->timestamp('core_provisional_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        $table = (new (config('core.auth.user_model', 'App\\Models\\User')))->getTable();

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('core_provisional_at');
        });
    }
};
