<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lockscreen_code', 4)->default('1234')->after('password');
            $table->unsignedSmallInteger('lockscreen_timeout')->default(10)->after('lockscreen_code');
            $table->boolean('otp_enabled')->default(false)->after('lockscreen_timeout');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['lockscreen_code', 'lockscreen_timeout', 'otp_enabled']);
        });
    }
};
