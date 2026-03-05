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
            $table->enum('schedule_type', ['relative', 'fixed', 'recurring'])->default('relative');
            $table->integer('delay_value')->nullable();
            $table->enum('delay_unit', ['minutes', 'hours', 'days', 'weeks'])->nullable();
            $table->dateTime('fixed_at')->nullable();
            $table->string('cron_expression')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent', 'Critical'])->default('Medium');
            $table->enum('default_assignee_type', ['admin', 'seller', 'buyer', 'harbor', 'creator', 'related_owner'])->default('admin');
            $table->boolean('notification_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_automation_templates');
    }
};
