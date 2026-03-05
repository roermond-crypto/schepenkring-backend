<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('New','Pending','To Do','In Progress','Done') NOT NULL DEFAULT 'New'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('To Do','In Progress','Done') NOT NULL DEFAULT 'To Do'");
            }
        }
    }
};
