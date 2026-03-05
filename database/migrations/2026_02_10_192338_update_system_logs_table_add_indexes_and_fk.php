<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {

            // ✅ Add index on created_at if it doesn't exist
            try {
                $table->index('created_at');
            } catch (\Throwable $e) {
                // ignore if index already exists
            }

            // ✅ Add foreign key to users if not exists
            try {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();
            } catch (\Throwable $e) {
                // ignore if FK already exists
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {

            // Drop foreign key safely
            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable $e) {}

            // Drop created_at index safely
            try {
                $table->dropIndex(['created_at']);
            } catch (\Throwable $e) {}
        });
    }
};
