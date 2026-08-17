<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.1 — the two user columns this app owns.
 *
 * Roles live in laravel-core's `core_roles` / `core_role_user`, so there is no
 * `is_admin` here. Organization membership columns come with card 1.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // E.164, e.g. +15551234567. Nullable: coordinators may never give one.
            $table->string('phone', 20)->nullable()->after('email_verified_at');
            $table->boolean('sms_opt_in')->default(false)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'sms_opt_in']);
        });
    }
};
