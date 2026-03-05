<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_page_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_permission_id')->constrained('page_permissions')->onDelete('cascade');
            $table->tinyInteger('permission_value')->default(0); // 0 = default, 1 = show, 2 = hide
            $table->timestamps();
            
            $table->unique(['user_id', 'page_permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_page_permissions');
    }
};