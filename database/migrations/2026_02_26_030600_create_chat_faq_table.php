<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_faq', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('harbor_id')->nullable();
            $table->string('language', 5)->nullable();
            $table->text('question');
            $table->text('best_answer');
            $table->unsignedInteger('thumbs_up_count')->default(0);
            $table->uuid('source_conversation_id')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index('harbor_id');
            $table->index('language');
            $table->index('thumbs_up_count');
            $table->index('source_conversation_id');

            $table->foreign('harbor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_admin_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('source_conversation_id')->references('id')->on('conversations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_faq');
    }
};
