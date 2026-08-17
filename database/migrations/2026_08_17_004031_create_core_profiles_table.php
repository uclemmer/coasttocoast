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

        Schema::create('core_profiles', function (Blueprint $table) use ($userModel): void {
            $table->id();

            /*
             * The user FK is resolved from the configured model so the column
             * type (int / uuid / ulid) always matches the host's users table.
             * Unique: a user has at most one profile.
             */
            if (is_string($userModel) && class_exists($userModel) && is_subclass_of($userModel, Model::class)) {
                /** @var Model $instance */
                $instance = new $userModel;

                $table->foreignIdFor($userModel, 'user_id')
                    ->constrained($instance->getTable(), $instance->getKeyName())
                    ->cascadeOnDelete();
            } else {
                $table->foreignId('user_id');
            }

            $table->string('display_name')->nullable();
            $table->text('bio')->nullable();

            // A path on the configured disk, never a URL — the disk decides how
            // a path becomes a URL, and that can change without a migration.
            $table->string('avatar_path')->nullable();

            $table->json('links')->nullable();
            $table->string('timezone')->nullable();
            $table->string('locale')->nullable();

            // Public profiles are opt-in, per user, always.
            $table->boolean('is_public')->default(false);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_profiles');
    }
};
