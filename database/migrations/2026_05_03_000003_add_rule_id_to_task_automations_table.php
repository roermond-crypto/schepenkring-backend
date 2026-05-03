<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_automations', function (Blueprint $table) {
            if (! Schema::hasColumn('task_automations', 'rule_id')) {
                $table->foreignId('rule_id')
                    ->nullable()
                    ->after('template_id')
                    ->constrained('task_automation_rules')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_automations', function (Blueprint $table) {
            if (Schema::hasColumn('task_automations', 'rule_id')) {
                $table->dropConstrainedForeignId('rule_id');
            }
        });
    }
};
