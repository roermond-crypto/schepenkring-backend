<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_ingestion_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 40)->index();
            $table->string('source_table', 120)->nullable();
            $table->string('source_reference')->nullable()->index();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedInteger('documents_count')->default(0);
            $table->unsignedInteger('chunks_count')->default(0);
            $table->unsignedInteger('embeddings_count')->default(0);
            $table->unsignedInteger('entities_count')->default(0);
            $table->unsignedInteger('failures_count')->default(0);
            $table->json('metadata')->nullable();
            $table->text('error_text')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_ingestion_runs');
    }
};
