<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_brain_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('knowledge_brain_questions')->nullOnDelete();
            $table->foreignId('approved_faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint', 255)->unique();
            $table->string('type', 40)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('title', 255);
            $table->string('source_type', 40)->default('system')->index();
            $table->string('question', 500)->nullable();
            $table->text('current_answer')->nullable();
            $table->text('suggested_answer')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('ai_score')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('first_detected_at')->nullable();
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'type', 'status']);
            $table->index(['location_id', 'last_detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_brain_suggestions');
    }
};
