<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id')->nullable();
            $table->unsignedBigInteger('harbor_id')->nullable();
            $table->uuid('contact_id')->nullable();
            $table->unsignedBigInteger('initiated_by_user_id')->nullable();
            $table->string('direction')->index();
            $table->string('status')->default('initiated')->index();
            $table->string('from_number', 32)->nullable();
            $table->string('to_number', 32)->nullable();
            $table->string('call_control_id')->nullable()->unique();
            $table->string('call_leg_id')->nullable()->index();
            $table->string('telnyx_call_session_id')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('billable_seconds')->nullable();
            $table->decimal('cost_eur', 10, 2)->nullable();
            $table->timestamp('charged_at')->nullable();
            $table->string('recording_url')->nullable();
            $table->string('recording_storage_path')->nullable();
            $table->string('language', 5)->nullable();
            $table->longText('transcript_text')->nullable();
            $table->unsignedInteger('latency_first_token_ms')->nullable();
            $table->unsignedInteger('latency_first_audio_ms')->nullable();
            $table->unsignedInteger('latency_total_ms')->nullable();
            $table->string('outcome')->nullable();
            $table->json('metadata')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['harbor_id', 'created_at']);
            $table->index(['direction', 'status']);

            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
            $table->foreign('harbor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('initiated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
