<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_brain_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('matched_faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->string('source_type', 40)->default('copilot')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('normalized_question', 255);
            $table->string('question', 500);
            $table->unsignedInteger('times_asked')->default(1);
            $table->decimal('confidence', 6, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'normalized_question'], 'kb_questions_location_question_unique');
            $table->index(['location_id', 'status', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_brain_questions');
    }
};
