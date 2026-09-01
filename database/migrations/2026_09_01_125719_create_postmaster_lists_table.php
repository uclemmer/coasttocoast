<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postmaster_lists', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            /*
             * The stable identifier a host writes in code: `MailingList::key('newsletter')`.
             * Renaming the display name must not break a call site, which is
             * why this exists separately from `name`.
             */
            $table->string('key')->unique();

            $table->string('name');
            $table->text('description')->nullable();

            /*
             * Which provider stream mail for this list goes out on. Broadcast
             * by default: keeping bulk and transactional apart is what stops a
             * badly received campaign from damaging the deliverability of a
             * receipt.
             */
            $table->string('stream')->default('broadcast');

            /*
             * Whether the public signup form will offer this list. A host can
             * keep an internal list -- staff announcements, say -- that nobody
             * can join from outside.
             */
            $table->boolean('is_public')->default(true);

            /*
             * Double opt-in. On by default, and turning it off is a decision
             * with legal weight: the confirmation is what produces the evidence
             * that somebody asked for this.
             */
            $table->boolean('requires_confirmation')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postmaster_lists');
    }
};
