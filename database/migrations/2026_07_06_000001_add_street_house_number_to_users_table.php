<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('street')->nullable()->after('address_line2');
            $table->string('house_number')->nullable()->after('street');
        });

        // Backfill: treat existing address_line1 as street, address_line2 as house_number
        DB::statement("UPDATE users SET street = address_line1 WHERE street IS NULL AND address_line1 IS NOT NULL");
        DB::statement("UPDATE users SET house_number = address_line2 WHERE house_number IS NULL AND address_line2 IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['street', 'house_number']);
        });
    }
};
