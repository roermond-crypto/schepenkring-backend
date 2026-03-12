<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->string('extension', 20)->nullable()->index();
            $table->string('source_type', 40)->default('document')->index();
            $table->string('status', 30)->default('uploaded')->index();
            $table->string('language', 5)->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->string('department')->nullable()->index();
            $table->string('visibility', 20)->default('internal')->index();
            $table->string('brand')->nullable()->index();
            $table->string('model')->nullable()->index();
            $table->json('tags')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedInteger('generated_qna_count')->default(0);
            $table->longText('extracted_text')->nullable();
            $table->text('processing_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_knowledge_documents');
    }
};
