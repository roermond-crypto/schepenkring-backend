<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('internal_code')->nullable();
            $table->string('trigger_event');
            $table->boolean('is_active')->default(true);
            $table->enum('target_role', ['client', 'employee', 'admin'])->default('employee');
            $table->enum('target_user_type', ['buyer', 'seller'])->nullable();
            $table->enum('assignee_rule', ['seller', 'creator', 'harbor_user', 'specific_user'])->default('harbor_user');
            $table->json('boat_types')->nullable();
            $table->unsignedInteger('boat_year_from')->nullable();
            $table->unsignedInteger('boat_year_to')->nullable();
            $table->string('location_filter')->nullable();
            $table->unsignedInteger('visibility_delay_hours')->default(0);
            $table->string('visibility_status')->nullable();
            $table->enum('visibility_status_source', ['related', 'boat', 'deal', 'bid', 'booking'])->nullable();
            $table->unsignedInteger('actionable_delay_hours')->nullable();
            $table->string('actionable_status')->nullable();
            $table->enum('actionable_status_source', ['related', 'boat', 'deal', 'bid', 'booking'])->nullable();
            $table->boolean('actionable_requires_internal_tasks_completed')->default(false);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['trigger_event', 'is_active']);
            $table->index(['target_role', 'target_user_type']);
            $table->index('location_id');
        });

        Schema::create('task_automation_rule_template', function (Blueprint $table) {
            $table->foreignId('rule_id')->constrained('task_automation_rules')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('task_automation_templates')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['rule_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_automation_rule_template');
        Schema::dropIfExists('task_automation_rules');
    }
};
