<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->string('sender_type'); // visitor|admin|ai|system
            $table->text('text')->nullable();
            $table->string('language', 5)->nullable();
            $table->string('channel')->default('web');
            $table->string('external_message_id')->nullable();
            $table->string('message_type')->default('text');
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index('sender_type');
            $table->index('external_message_id');

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
