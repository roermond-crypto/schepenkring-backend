<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the column does NOT exist before adding it
        if (!Schema::hasColumn('yachts', 'user_id')) {
            Schema::table('yachts', function (Blueprint $table) {
                // We make it nullable so existing yachts (admin created) don't break.
                // constrained() automatically links it to 'id' on the 'users' table.
                $table->foreignId('user_id')
                      ->nullable()
                      ->after('id')
                      ->constrained('users')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('yachts', 'user_id')) {
            Schema::table('yachts', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};