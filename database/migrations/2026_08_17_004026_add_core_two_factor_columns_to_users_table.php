<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the package's two-factor columns to the host application's users table.
 * `core_` prefixed like every other column this package owns — the TOTP
 * implementation is in-house, so the names are ours.
 *
 * Both secret and recovery codes are encrypted by the model cast, hence text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->usersTable(), function (Blueprint $table): void {
            $table->text('core_two_factor_secret')->nullable();
            $table->text('core_two_factor_recovery_codes')->nullable();
            $table->timestamp('core_two_factor_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->usersTable(), function (Blueprint $table): void {
            $table->dropColumn([
                'core_two_factor_secret',
                'core_two_factor_recovery_codes',
                'core_two_factor_confirmed_at',
            ]);
        });
    }

    protected function usersTable(): string
    {
        $userModel = config('core.auth.user_model', 'App\\Models\\User');

        if (is_string($userModel) && class_exists($userModel) && is_subclass_of($userModel, Model::class)) {
            /** @var Model $instance */
            $instance = new $userModel;

            return $instance->getTable();
        }

        return 'users';
    }
};
