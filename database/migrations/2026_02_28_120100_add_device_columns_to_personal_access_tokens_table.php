<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('device_id')->nullable()->index()->after('name');
            $table->string('device_name')->nullable()->after('device_id');
            $table->string('browser')->nullable()->after('device_name');
            $table->string('os')->nullable()->after('browser');
            $table->text('user_agent')->nullable()->after('os');
            $table->string('ip_address', 45)->nullable()->after('user_agent');
            $table->string('ip_country', 8)->nullable()->after('ip_address');
            $table->string('ip_city', 120)->nullable()->after('ip_country');
            $table->string('ip_asn', 32)->nullable()->after('ip_city');
            $table->string('auth_strength', 20)->nullable()->after('ip_asn');
            $table->dateTime('first_seen_at')->nullable()->after('auth_strength');
            $table->dateTime('last_seen_at')->nullable()->after('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'device_id',
                'device_name',
                'browser',
                'os',
                'user_agent',
                'ip_address',
                'ip_country',
                'ip_city',
                'ip_asn',
                'auth_strength',
                'first_seen_at',
                'last_seen_at',
            ]);
        });
    }
};
