<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_translation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('faq_id');
            $table->string('language', 5);
            $table->text('question');
            $table->text('answer');
            $table->text('long_description')->nullable();
            $table->enum('long_description_status', ['pending', 'generating', 'ready', 'failed'])
                ->default('pending');
            $table->boolean('needs_review')->default(false);
            $table->string('source_language', 5)->nullable();
            $table->uuid('translated_from_translation_id')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique(['faq_id', 'language']);
            $table->index('language');
            $table->index('needs_review');
            $table->index('long_description_status');
            $table->index('translated_from_translation_id');

            $table->foreign('faq_id')->references('id')->on('faq')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_translation');
    }
};
