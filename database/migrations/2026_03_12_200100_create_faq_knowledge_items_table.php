<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('faq_knowledge_documents')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('approved_faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('chunk_index')->default(0)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('source_type', 40)->default('document')->index();
            $table->string('language', 5)->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->string('department')->nullable()->index();
            $table->string('visibility', 20)->default('internal')->index();
            $table->string('brand')->nullable()->index();
            $table->string('model')->nullable()->index();
            $table->json('tags')->nullable();
            $table->string('question');
            $table->text('answer');
            $table->text('source_excerpt')->nullable();
            $table->text('review_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_knowledge_items');
    }
};
