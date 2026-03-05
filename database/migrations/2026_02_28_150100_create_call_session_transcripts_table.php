<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_session_transcripts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('call_session_id');
            $table->uuid('conversation_id')->nullable();
            $table->string('speaker', 20)->default('unknown');
            $table->longText('text');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->boolean('is_final')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['call_session_id', 'sequence']);
            $table->index(['conversation_id', 'created_at']);

            $table->foreign('call_session_id')->references('id')->on('call_sessions')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_session_transcripts');
    }
};
