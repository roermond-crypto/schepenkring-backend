<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained('yachts')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('video_templates')->restrictOnDelete();
            $table->enum('variation', ['horizontal', 'vertical', 'square', 'teaser'])->default('horizontal');
            $table->enum('status', ['draft', 'ai_generated', 'validation_failed', 'approved', 'rendering', 'rendered', 'failed'])->default('draft');
            $table->json('ai_input_json')->nullable();
            $table->json('ai_output_json')->nullable();
            $table->json('final_plan_json')->nullable();
            $table->json('validation_errors')->nullable();
            $table->string('render_job_id')->nullable();
            $table->string('render_output_url')->nullable();
            $table->timestamp('render_started_at')->nullable();
            $table->timestamp('render_completed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_plans');
    }
};
