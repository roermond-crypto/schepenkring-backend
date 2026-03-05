<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {

            // ---------- PRIORITY ----------
            if (Schema::hasColumn('tasks', 'priority')) {
                $table->dropColumn('priority');
            }

            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent', 'Critical'])
                  ->default('Medium');


            // ---------- USER_ID ----------
            if (Schema::hasColumn('tasks', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            $table->foreignId('user_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('users')
                  ->cascadeOnDelete();


            // ---------- TYPE ----------
            if (Schema::hasColumn('tasks', 'type')) {
                $table->dropColumn('type');
            }

            $table->enum('type', ['assigned', 'personal'])
                  ->default('assigned');


            // ---------- CREATED_BY ----------
            if (Schema::hasColumn('tasks', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {

            if (Schema::hasColumn('tasks', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('tasks', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('tasks', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('tasks', 'priority')) {
                $table->dropColumn('priority');
            }

            // Restore original priority enum
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])
                  ->default('Medium');
        });
    }
};
