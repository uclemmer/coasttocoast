<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card 1.2 — one fair year.
 *
 * Almost everything in the app is scoped to an event: registrations, grants,
 * rosters, interest sign-ups and campaign audiences all hang off it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // The counselor reception that precedes the fair; not every year has one.
            $table->dateTime('reception_starts_at')->nullable();

            $table->string('venue_name');
            $table->text('venue_address');

            // Integer cents (doc 02 convention 3). The list price before any
            // grant — what an organization actually pays comes from
            // Event::priceFor(), never from this column directly.
            $table->unsignedInteger('price_cents')->default(0);

            // Null means uncapped. When set, it caps *occupying* registrations
            // (pending payment + confirmed), not confirmed alone — otherwise a
            // run of mailed checks could oversell the room.
            $table->unsignedInteger('capacity')->nullable();

            // Null on either side means "no bound in that direction" (R1.8).
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();

            $table->boolean('is_published')->default(false);

            $table->timestamps();

            // The Last Year page and every cross-year audience order published
            // events by start date (doc 07 §2 rule 5).
            $table->index(['is_published', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
