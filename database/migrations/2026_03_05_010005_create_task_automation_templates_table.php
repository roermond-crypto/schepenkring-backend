<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_automation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('trigger_event');
            $table->string('schedule_type')->default('relative');
            $table->integer('delay_value')->nullable();
            $table->string('delay_unit')->nullable();
            $table->dateTime('fixed_at')->nullable();
            $table->string('cron_expression')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('Medium');
            $table->string('default_assignee_type')->default('admin');
            $table->boolean('notification_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['trigger_event', 'is_active']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_automation_templates');
    }
};
