<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userModel = config('core.auth.user_model', 'App\\Models\\User');

        Schema::create('core_legal_versions', function (Blueprint $table) use ($userModel): void {
            $table->ulid('id')->primary();

            $table->foreignId('document_id')
                ->constrained('core_legal_documents')
                ->cascadeOnDelete();

            $table->unsignedInteger('version');

            // The authored source, placeholders and all. Rendered on read —
            // never through Blade, see LegalRenderer.
            $table->longText('body');

            /*
             * The frozen variable snapshot. Null on a draft, filled at publish,
             * never touched again: this is why a version archived two years ago
             * still shows the address it showed then.
             */
            $table->json('variables')->nullable();

            // "What changed" — shown in the public version history.
            $table->string('summary')->nullable();

            /*
             * When this version takes over. Separate from `published_at`
             * because publishing ahead of time is normal: "these terms change
             * on the first of next month" is one row, published today,
             * effective later.
             */
            $table->timestamp('effective_at')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();

            if (is_string($userModel) && class_exists($userModel) && is_subclass_of($userModel, Model::class)) {
                /** @var Model $instance */
                $instance = new $userModel;

                // nullOnDelete: who published it is useful, but the version has
                // to outlive the employee who pressed the button.
                $table->foreignIdFor($userModel, 'published_by')
                    ->nullable()
                    ->constrained($instance->getTable(), $instance->getKeyName())
                    ->nullOnDelete();
            } else {
                $table->foreignId('published_by')->nullable();
            }

            $table->timestamps();

            // A reference to "v3 of the privacy policy" must mean exactly one
            // row, forever.
            $table->unique(['document_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_legal_versions');
    }
};
