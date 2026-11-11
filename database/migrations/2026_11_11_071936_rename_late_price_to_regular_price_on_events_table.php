<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Late price" becomes "regular price" (owner, 2026-11-11; docs/29).
 *
 * The price after December 31 is the regular price, and the end of the
 * early bird is a price increase, not a deadline. The deadline is
 * `registration_closes_at`. The columns are renamed so the schema says what
 * the owner says.
 *
 * A rename migration rather than an edit to the one that added them, because
 * that one was already pushed and run. Each column is renamed in its own
 * statement, which SQLite needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('late_price_cents', 'regular_price_cents');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('late_price_starts_at', 'regular_price_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('regular_price_cents', 'late_price_cents');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('regular_price_starts_at', 'late_price_starts_at');
        });
    }
};
