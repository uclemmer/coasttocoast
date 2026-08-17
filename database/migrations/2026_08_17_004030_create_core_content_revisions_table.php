<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_content_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('content_id')->constrained('core_contents')->cascadeOnDelete();

            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('format');
            $table->json('meta')->nullable();
            $table->string('author_id')->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_content_revisions');
    }
};
