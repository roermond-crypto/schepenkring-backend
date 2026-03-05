<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_errors', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('sentry_issue_id')->nullable()->unique();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('level', 50)->nullable();
            $table->string('project')->nullable();
            $table->string('environment', 50)->nullable();
            $table->string('release', 50)->nullable();
            $table->string('source')->nullable(); // frontend/backend
            $table->string('route')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('occurrences_count')->default(0);
            $table->unsignedInteger('users_affected')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status', 50)->default('unresolved');
            $table->json('tags')->nullable();
            $table->json('last_event_sample_json')->nullable();
            $table->text('ai_user_message_nl')->nullable();
            $table->text('ai_user_message_en')->nullable();
            $table->text('ai_user_message_de')->nullable();
            $table->text('ai_dev_summary')->nullable();
            $table->string('ai_category')->nullable();
            $table->string('ai_severity')->nullable();
            $table->json('ai_user_steps')->nullable();
            $table->json('ai_suggested_checks')->nullable();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('ignore_until')->nullable();
            $table->string('ignore_release')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'level', 'environment', 'release']);
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_errors');
    }
};
