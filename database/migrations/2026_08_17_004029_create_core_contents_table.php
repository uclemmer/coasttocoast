<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_contents', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->string('type')->index();
            $table->string('slug');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('format')->default('markdown');
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();

            $table->string('author_id')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * Unique per type. Soft-deleted rows still occupy their slug: this
             * is a plain unique index, so a slug is only freed on force delete.
             * That is deliberate — restoring trashed content must not fail
             * because something else claimed its URL in the meantime.
             *
             * On MySQL/Postgres a partial index excluding deleted rows would
             * free slugs sooner; if you want that, replace this with a raw
             * partial index and accept that restore can then collide.
             */
            $table->unique(['type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_contents');
    }
};
