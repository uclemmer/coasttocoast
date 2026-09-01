<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postmaster_subscribers', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            /*
             * An address this application knows about, independent of any one
             * list. Stored and matched lowercased, the same rule the
             * suppression list follows -- a subscriber who typed a capital
             * letter must not become a second person.
             */
            $table->string('email')->unique();

            $table->string('name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postmaster_subscribers');
    }
};
