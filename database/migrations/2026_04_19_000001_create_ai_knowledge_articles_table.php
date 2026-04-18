<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('match_type', 40);
            $table->string('match_value')->nullable();
            $table->json('tags')->nullable();
            $table->string('language', 8)->default('nl');
            $table->string('status', 20)->default('active');
            $table->string('pinecone_id')->nullable();
            $table->timestamp('last_embedded_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index('match_type');
            $table->index('match_value');
            $table->index('status');
            $table->index('language');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_articles');
    }
};
