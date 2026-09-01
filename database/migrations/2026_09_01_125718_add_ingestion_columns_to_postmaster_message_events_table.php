<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingestion needs three things the original events table did not have.
 *
 * A separate migration rather than an edit to the create stub, because that
 * stub has already been published and run in three hosts. Editing it would give
 * new installs the columns and leave existing ones without, silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postmaster_message_events', function (Blueprint $table): void {
            /*
             * The idempotency key. Providers retry, and a retry must not count
             * a second open or re-suppress an address.
             *
             * Nullable because rows written before ingestion existed have none,
             * and unique so the database is the backstop even if two webhook
             * deliveries race. SQLite and MySQL both allow many nulls in a
             * unique index, which is what makes the two facts compatible.
             */
            $table->string('fingerprint', 64)->nullable()->unique()->after('id');

            /*
             * Who the event was about. Denormalised from the payload on purpose:
             * an event can arrive for a message this application never logged,
             * and the address is the only useful thing left when `message_id`
             * is null.
             */
            $table->string('recipient')->nullable()->index()->after('event');
        });

        /*
         * `message_id` becomes nullable for the same reason. Events arrive for
         * mail sent before this package was installed, and for mail sent by
         * something else on the same provider server; dropping those on the
         * floor would lose the suppression they carry.
         */
        Schema::table('postmaster_message_events', function (Blueprint $table): void {
            $table->ulid('message_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('postmaster_message_events', function (Blueprint $table): void {
            $table->dropUnique(['fingerprint']);
            $table->dropColumn(['fingerprint', 'recipient']);
        });
    }
};
