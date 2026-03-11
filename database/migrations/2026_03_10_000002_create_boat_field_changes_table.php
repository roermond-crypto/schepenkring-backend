<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_field_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained('yachts')->cascadeOnDelete();
            $table->string('field_name', 120);
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->string('changed_by_type', 20)->default('user');
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 20)->nullable();
            $table->decimal('confidence_before', 5, 4)->nullable();
            $table->uuid('ai_session_id')->nullable();
            $table->string('model_name', 120)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('correction_label', 60)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['yacht_id', 'field_name', 'created_at']);
            $table->index(['changed_by_type', 'created_at']);
            $table->index(['source_type', 'created_at']);
            $table->index(['ai_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_field_changes');
    }
};

