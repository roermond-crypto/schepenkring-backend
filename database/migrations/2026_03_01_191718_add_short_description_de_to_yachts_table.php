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
        Schema::table('yachts', function (Blueprint $table) {
            $table->text('short_description_de')->nullable()->after('short_description_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn('short_description_de');
        });
    }
};
