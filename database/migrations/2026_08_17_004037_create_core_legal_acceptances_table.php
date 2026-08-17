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

        Schema::create('core_legal_acceptances', function (Blueprint $table) use ($userModel): void {
            $table->ulid('id')->primary();

            if (is_string($userModel) && class_exists($userModel) && is_subclass_of($userModel, Model::class)) {
                /** @var Model $instance */
                $instance = new $userModel;

                /*
                 * cascadeOnDelete: when an account is erased — a deletion
                 * request, say — the record of what they agreed to goes with
                 * it. Keeping consent records for a person who asked to be
                 * forgotten would be a strange way to honour the request.
                 */
                $table->foreignIdFor($userModel, 'user_id')
                    ->constrained($instance->getTable(), $instance->getKeyName())
                    ->cascadeOnDelete();
            } else {
                $table->foreignId('user_id');
            }

            $table->foreignUlid('version_id')
                ->constrained('core_legal_versions')
                ->cascadeOnDelete();

            // Where it happened: 'registration', 'checkout', 'interstitial'.
            $table->string('context')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // No updated_at: an acceptance is never updated.
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['user_id', 'version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_legal_acceptances');
    }
};
