<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An early/late fee, and a hotel booking link, per fair (docs/29).
 *
 * The 2027 fair is $275 through December 31 and $325 after it. `price_cents`
 * stays the early (or only) fee. `late_price_cents` and `late_price_starts_at`
 * are both nullable, and the late fee applies only when both are set, so every
 * existing fair keeps its single price unchanged.
 *
 * `hotel_booking_url` is the hotel's group-rate link. It goes in the
 * confirmation emails, and it is a column rather than a hard-coded URL because
 * the hotel issues a new link every year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('late_price_cents')->nullable()->after('price_cents');
            $table->dateTime('late_price_starts_at')->nullable()->after('late_price_cents');
            $table->string('hotel_booking_url', 2048)->nullable()->after('venue_address');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['late_price_cents', 'late_price_starts_at', 'hotel_booking_url']);
        });
    }
};
