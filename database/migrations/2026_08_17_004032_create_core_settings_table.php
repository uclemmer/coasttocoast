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

        Schema::create('core_settings', function (Blueprint $table) use ($userModel): void {
            $table->id();

            $table->string('scope')->index();          // 'app' | 'user'

            /*
             * The real foreign key, so a deleted user takes their settings with
             * them. Null for app scope.
             */
            if (is_string($userModel) && class_exists($userModel) && is_subclass_of($userModel, Model::class)) {
                /** @var Model $instance */
                $instance = new $userModel;

                $table->foreignIdFor($userModel, 'user_id')
                    ->nullable()
                    ->constrained($instance->getTable(), $instance->getKeyName())
                    ->cascadeOnDelete();
            } else {
                $table->foreignId('user_id')->nullable();
            }

            /*
             * `user_id` stringified, or '' for app scope — and the reason this
             * column exists rather than the unique index simply naming
             * `user_id`:
             *
             * SQLite, MySQL and Postgres all treat NULLs in a unique index as
             * DISTINCT from one another. A unique index on
             * (scope, user_id, key) therefore permits any number of app-scope
             * rows for the same key, because every one of them has user_id
             * NULL. The whole point of this table is that a key resolves to one
             * value; a constraint that silently does not constrain the app
             * scope is worse than none.
             */
            $table->string('owner_key')->default('');

            $table->string('key');
            $table->json('value')->nullable();

            $table->timestamps();

            $table->unique(['scope', 'owner_key', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_settings');
    }
};
