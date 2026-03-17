<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_fields', function (Blueprint $table) {
            $table->id();
            $table->string('internal_key', 120)->unique();
            $table->json('labels_json');
            $table->string('field_type', 50);
            $table->string('block_key', 80);
            $table->string('step_key', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('storage_relation', 80)->nullable();
            $table->string('storage_column', 120);
            $table->boolean('ai_relevance')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['step_key', 'block_key', 'sort_order']);
            $table->index(['is_active', 'step_key']);
            $table->index(['storage_relation', 'storage_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_fields');
    }
};
