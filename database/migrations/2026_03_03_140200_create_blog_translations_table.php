<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->string('excerpt')->nullable();
            $table->longText('content');
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('status', 20)->default('AI_DRAFT');
            $table->string('source_hash', 64)->nullable();
            $table->string('translated_from_hash', 64)->nullable();
            $table->boolean('is_legal')->default(false);
            $table->timestamps();

            $table->unique(['blog_id', 'locale']);
            $table->index(['locale', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_translations');
    }
};
