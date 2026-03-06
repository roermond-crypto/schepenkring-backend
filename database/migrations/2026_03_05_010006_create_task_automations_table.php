<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('task_automation_templates')->cascadeOnDelete();
            $table->string('trigger_event');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at');
            $table->string('status')->default('pending');
            $table->foreignId('created_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->text('last_error')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['related_type', 'related_id']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_automations');
    }
};
