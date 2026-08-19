<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — the college or university that attends the fair (D8).
 *
 * The organization, not the person, is the unit that registers, holds grants
 * and appears on the roster. Reps point at it (`users.organization_id`), so it
 * survives rep turnover; there is deliberately no owner column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Lowercased, punctuation-stripped form of `name`. Powers the
            // duplicate soft-check at signup (R2.7) and the roster import's
            // matching (card 6.6). Indexed, not unique — near-duplicates are
            // meant to be created and then merged by a human, never blocked.
            $table->string('normalized_name')->index();

            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();

            $table->string('admissions_office')->nullable();
            // The campaign fallback: mailed when the org has no active reps
            // (doc 07 §2 rule 1), which is how a school with rep turnover stays
            // on the win-back list.
            $table->string('admissions_email')->nullable();
            $table->string('admissions_phone', 20)->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();

            // The rep who created it during signup; null for admin entry and
            // for historical import (card 6.6).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
