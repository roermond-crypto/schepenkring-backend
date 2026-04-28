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
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->string('formatted_address')->nullable()->after('address_line_2');
            $table->string('street')->nullable()->after('formatted_address');
            $table->string('house_number')->nullable()->after('street');
            $table->decimal('latitude', 10, 8)->nullable()->after('house_number');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('place_id')->nullable()->after('longitude');
        });

        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->string('formatted_address')->nullable()->after('address_line_2');
            $table->string('street')->nullable()->after('formatted_address');
            $table->string('house_number')->nullable()->after('street');
            $table->decimal('latitude', 10, 8)->nullable()->after('house_number');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('place_id')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn(['formatted_address', 'street', 'house_number', 'latitude', 'longitude', 'place_id']);
        });

        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->dropColumn(['formatted_address', 'street', 'house_number', 'latitude', 'longitude', 'place_id']);
        });
    }
};
