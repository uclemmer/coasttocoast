<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label')->nullable();
            $table->string('group')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_permissions');
    }
};
