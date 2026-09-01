<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A downloadable file per FAQ answer.
 *
 * Added for the signed W-9 that doc 11's owner queue has been waiting on — the
 * queue said "Admin → FAQ (and a file to upload)" and the FAQ screen had no
 * upload, so the panel could not keep that promise.
 *
 * Deliberately a generic attachment rather than a `w9_path` on some settings
 * table. The W-9 is the case that exists today; a floor plan, a parking map or
 * a conduct policy are the same shape, and a column named after one document
 * would have to be joined by another the first time a second one appeared.
 *
 * `attachment_name` holds the filename the coordinator uploaded. The stored
 * name is randomised, and somebody filing a W-9 into their accounts-payable
 * system should get `coast-to-coast-w9.pdf` rather than a hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faq_items', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('answer');
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('faq_items', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
    }
};
