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

        Schema::create('core_role_user', function (Blueprint $table) use ($userModel) {
            $table->foreignId('role_id')->constrained('core_roles')->cascadeOnDelete();

            // Resolve the user FK from the configured model so the column type
            // (int / uuid / ulid) always matches the host application's users table.
            if (is_string($userModel) && class_exists($userModel) && is_subclass_of($userModel, Model::class)) {
                /** @var Model $instance */
                $instance = new $userModel;

                $table->foreignIdFor($userModel, 'user_id')
                    ->constrained($instance->getTable(), $instance->getKeyName())
                    ->cascadeOnDelete();
            } else {
                $table->foreignId('user_id');
            }

            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_role_user');
    }
};
