<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ Drop table if it already exists
        Schema::dropIfExists('activity_logs');

        // ✅ Recreate table cleanly
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->string('entity_name')->nullable();
            $table->string('action');
            $table->text('description');
            $table->enum('severity', ['info', 'success', 'warning', 'error'])->default('info');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['created_at']);
            $table->index(['severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
