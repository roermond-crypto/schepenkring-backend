<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_error_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('conversation_id')->nullable();
            $table->string('message_id')->nullable();
            $table->string('email')->nullable();
            $table->string('subject')->nullable();
            $table->text('description');
            $table->string('page_url')->nullable();
            $table->string('error_reference', 50)->nullable();
            $table->string('source', 30)->default('form');
            $table->string('status', 30)->default('open');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source', 'status']);
            $table->index('user_id');
            $table->index('platform_error_id');
            $table->index('conversation_id');

            $table->foreign('platform_error_id')->references('id')->on('platform_errors')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_reports');
    }
};
