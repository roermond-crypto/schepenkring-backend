<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yacht_images', function (Blueprint $table) {
            $table->string('caption', 255)->nullable()->after('part_name');
        });
    }

    public function down(): void
    {
        Schema::table('yacht_images', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};
