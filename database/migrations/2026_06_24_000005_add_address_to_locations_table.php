<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('address_line1')->nullable()->after('delete_reason');
            $table->string('street_number', 20)->nullable()->after('address_line1');
            $table->string('postal_code', 20)->nullable()->after('street_number');
            $table->string('city', 120)->nullable()->after('postal_code');
            $table->string('country', 120)->nullable()->after('city');
            $table->string('phone', 30)->nullable()->after('country');
            $table->string('email', 255)->nullable()->after('phone');
            $table->string('website', 500)->nullable()->after('email');
            $table->decimal('latitude', 10, 7)->nullable()->after('website');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'address_line1', 'street_number', 'postal_code', 'city', 'country',
                'phone', 'email', 'website', 'latitude', 'longitude',
            ]);
        });
    }
};
