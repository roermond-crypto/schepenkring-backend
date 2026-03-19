<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_entities', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('source_table', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('language', 5)->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['type', 'source_table', 'source_id'], 'knowledge_entities_source_unique');
            $table->index(['source_table', 'source_id']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entities');
    }
};
