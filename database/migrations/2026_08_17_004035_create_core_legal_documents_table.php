<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_legal_documents', function (Blueprint $table): void {
            $table->id();

            // The stable identifier code refers to (`privacy`, `terms`,
            // `refund`). Unique because two documents of the same kind is not a
            // state this feature has an answer for.
            $table->string('key')->unique();

            $table->string('title');

            // Cosmetic and changeable — it appears in URLs, `key` does not.
            $table->string('slug')->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_legal_documents');
    }
};
