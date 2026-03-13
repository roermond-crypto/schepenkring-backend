<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add boat_type_filter to existing templates table
        Schema::table('task_automation_templates', function (Blueprint $table) {
            $table->json('boat_type_filter')->nullable()->after('is_active');
        });

        // Create template items table for multi-task templates
        Schema::create('task_automation_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('task_automation_templates')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('Medium');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_automation_template_items');

        Schema::table('task_automation_templates', function (Blueprint $table) {
            $table->dropColumn('boat_type_filter');
        });
    }
};
