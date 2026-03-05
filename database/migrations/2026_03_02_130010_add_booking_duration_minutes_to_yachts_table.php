<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            if (!Schema::hasColumn('yachts', 'booking_duration_minutes')) {
                $table->unsignedSmallInteger('booking_duration_minutes')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            if (Schema::hasColumn('yachts', 'booking_duration_minutes')) {
                $table->dropColumn('booking_duration_minutes');
            }
        });
    }
};
