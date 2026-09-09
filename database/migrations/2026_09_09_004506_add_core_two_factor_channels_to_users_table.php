<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-factor delivery channels, added in 0.7.0.
 *
 * A SEPARATE stub rather than a change to
 * `add_core_two_factor_columns_to_users_table`: that one has already run on
 * every host using two-factor, and an edited migration is one that will never
 * run again there. A host upgrading publishes this file and migrates; a fresh
 * install runs both, in order.
 *
 * Null means "the authenticator app", which is what every account enrolled
 * before this column existed was using. Reading null as "no channels" would
 * lock those accounts out at the next challenge, so
 * `HasTwoFactorAuth::twoFactorChannels()` reads it as `['app']`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->usersTable();

        if (Schema::hasColumn($table, 'core_two_factor_channels')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->json('core_two_factor_channels')->nullable();
        });
    }

    public function down(): void
    {
        $table = $this->usersTable();

        if (! Schema::hasColumn($table, 'core_two_factor_channels')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('core_two_factor_channels');
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
