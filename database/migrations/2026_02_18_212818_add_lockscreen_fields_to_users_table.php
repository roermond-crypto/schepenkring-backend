<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $column) {
            // Adding the 4-digit PIN column (defaulting to 1234)
            $column->string('lockscreen_code', 4)->default('1234')->after('password');
            
            // Adding the OTP Toggle column
            $column->boolean('otp_enabled')->default(false)->after('lockscreen_code');
            
            // Adding the timeout column if you haven't already
            if (!Schema::hasColumn('users', 'lockscreen_timeout')) {
                $column->integer('lockscreen_timeout')->default(10)->after('otp_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $column) {
            $column->dropColumn(['lockscreen_code', 'otp_enabled', 'lockscreen_timeout']);
        });
    }
};