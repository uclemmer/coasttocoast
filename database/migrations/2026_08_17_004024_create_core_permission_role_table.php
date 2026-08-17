<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('core_permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('core_roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_permission_role');
    }
};
