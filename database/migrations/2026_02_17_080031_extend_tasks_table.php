<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns
        Schema::table('tasks', function (Blueprint $table) {
            // assignment_status for employee assignments
            $table->enum('assignment_status', ['pending', 'accepted', 'rejected'])
                  ->default('accepted')
                  ->after('status');
        });

        // Convert existing Urgent/Critical to High
        DB::statement("UPDATE tasks SET priority = 'High' WHERE priority IN ('Urgent', 'Critical')");

        // Change priority column to new enum (MySQL syntax – skip for SQLite)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE tasks MODIFY priority ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium'");
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('assignment_status');
        });

        // Reverting priority is not recommended; skip for down.
    }
};
