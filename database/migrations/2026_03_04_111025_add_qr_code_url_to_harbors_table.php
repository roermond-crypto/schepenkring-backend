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
        Schema::table('harbors', function (Blueprint $table) {
            $table->string('qr_code_url')->nullable()->after('last_place_details_fetch_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harbors', function (Blueprint $table) {
            $table->dropColumn('qr_code_url');
        });
    }
};
