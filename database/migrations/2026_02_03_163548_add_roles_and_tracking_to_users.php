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
        Schema::table('users', function (Blueprint $table) {
            // 1. Check and add Role-based columns with defaults
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('Customer')->after('password');
            }
            
            if (!Schema::hasColumn('users', 'access_level')) {
                $table->string('access_level')->default('None')->after('status');
            }

            // 2. Check and add Registration Tracking columns
            if (!Schema::hasColumn('users', 'registration_ip')) {
                $table->string('registration_ip')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'user_agent')) {
                $table->text('user_agent')->nullable();
            }
            
            if (!Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop columns if they exist
            $columns = ['role', 'access_level', 'registration_ip', 'user_agent', 'terms_accepted_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};