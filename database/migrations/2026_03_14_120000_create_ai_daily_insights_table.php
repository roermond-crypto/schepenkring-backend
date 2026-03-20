<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_daily_insights', function (Blueprint $table) {
            $table->id();
            // DATETIME is safer than TIMESTAMP here for strict MySQL installs.
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->string('product')->nullable();
            $table->string('environment', 50);
            $table->string('timezone', 100)->default('UTC');
            $table->string('status', 50)->default('completed');
            $table->string('overall_status', 50)->nullable();
            $table->string('headline')->nullable();
            $table->string('model')->nullable();
            $table->string('openai_response_id')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('summary_json')->nullable();
            $table->json('top_findings_json')->nullable();
            $table->json('performance_issues_json')->nullable();
            $table->json('security_signals_json')->nullable();
            $table->json('priority_actions_json')->nullable();
            $table->json('raw_input_json')->nullable();
            $table->json('raw_output_json')->nullable();
            $table->json('usage_json')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'period_end', 'environment'], 'ai_daily_insights_period_environment_unique');
            $table->index(['status', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_daily_insights');
    }
};
