<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'reminder_at')) {
                $table->dateTime('reminder_at')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('tasks', 'reminder_sent_at')) {
                $table->dateTime('reminder_sent_at')->nullable()->after('reminder_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'reminder_sent_at')) {
                $table->dropColumn('reminder_sent_at');
            }
            if (Schema::hasColumn('tasks', 'reminder_at')) {
                $table->dropColumn('reminder_at');
            }
        });
    }
};
