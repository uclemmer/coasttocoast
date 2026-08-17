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

        Schema::create('core_contact_submissions', function (Blueprint $table) use ($userModel): void {
            $table->ulid('id')->primary();

            /*
             * Nullable, and `nullOnDelete`: most submissions arrive from people
             * with no account, and a submission must outlive whatever account
             * it was later attributed to. The message is the record; the user
             * link is an optimisation for finding their other messages.
             */
            if (is_string($userModel) && class_exists($userModel) && is_subclass_of($userModel, Model::class)) {
                /** @var Model $instance */
                $instance = new $userModel;

                $table->foreignIdFor($userModel, 'user_id')
                    ->nullable()
                    ->constrained($instance->getTable(), $instance->getKeyName())
                    ->nullOnDelete();
            } else {
                $table->foreignId('user_id')->nullable();
            }

            $table->string('name');
            $table->string('email')->index();
            $table->string('subject')->nullable();
            $table->text('message');

            /*
             * Stored for abuse handling, and deliberately plain: this is the
             * only place the package keeps a requester's IP, the retention is
             * the host's to decide, and `core:prune-contact-submissions` exists
             * so "decide" can mean "after ninety days".
             */
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_contact_submissions');
    }
};
