<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_ai_extractions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id')->unique();
            $table->foreignId('yacht_id')->nullable()->constrained('yachts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('completed');
            $table->string('model_name', 120)->nullable();
            $table->string('model_version', 120)->nullable();
            $table->text('hint_text')->nullable();
            $table->unsignedSmallInteger('image_count')->default(0);
            $table->json('raw_output_json')->nullable();
            $table->json('normalized_fields_json')->nullable();
            $table->json('field_confidence_json')->nullable();
            $table->json('field_sources_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();

            $table->index(['yacht_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_ai_extractions');
    }
};

