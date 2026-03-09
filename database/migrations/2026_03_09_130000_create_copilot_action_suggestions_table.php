<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_action_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('suggestion_key')->unique();
            $table->string('suggestion_type', 20)->default('action')->index();
            $table->foreignId('target_copilot_action_id')->nullable()->constrained('copilot_actions')->nullOnDelete();
            $table->foreignId('created_action_id')->nullable()->constrained('copilot_actions')->nullOnDelete();
            $table->string('action_id', 120)->nullable();
            $table->string('title');
            $table->string('short_description')->nullable();
            $table->string('module')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('route_template')->nullable();
            $table->string('query_template')->nullable();
            $table->json('required_params')->nullable();
            $table->json('input_schema')->nullable();
            $table->json('phrases')->nullable();
            $table->json('example_prompts')->nullable();
            $table->string('permission_key')->nullable();
            $table->string('required_role')->nullable();
            $table->string('risk_level', 20)->default('low');
            $table->boolean('confirmation_required')->default(false);
            $table->decimal('confidence', 5, 3)->default(0);
            $table->unsignedInteger('evidence_count')->default(0);
            $table->json('evidence')->nullable();
            $table->json('pinecone_matches')->nullable();
            $table->text('reasoning')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_action_suggestions');
    }
};
