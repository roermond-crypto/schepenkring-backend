<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_automation_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->nullable()->constrained('task_automation_rules')->nullOnDelete();
            $table->string('trigger_event');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('result', ['success', 'skipped', 'failed']);
            $table->string('reason')->nullable();
            $table->json('created_task_ids')->nullable();
            $table->json('matched_conditions')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['trigger_event', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_automation_execution_logs');
    }
};
