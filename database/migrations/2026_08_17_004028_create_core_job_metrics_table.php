<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Row per job execution. Deliberately narrow and aggressively pruned — this is
 * throughput telemetry, not an audit log. Laravel's own `jobs` / `failed_jobs`
 * tables are read in place and never altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_job_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('job_class')->index();
            $table->string('queue')->nullable()->index();
            $table->string('connection')->nullable();
            $table->string('status')->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('ran_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_job_metrics');
    }
};
