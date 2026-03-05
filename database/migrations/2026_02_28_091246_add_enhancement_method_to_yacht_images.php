<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yacht_images', function (Blueprint $table) {
            $table->string('enhancement_method')->nullable()->default('none')->after('quality_flags');
        });
    }

    public function down(): void
    {
        Schema::table('yacht_images', function (Blueprint $table) {
            $table->dropColumn('enhancement_method');
        });
    }
};
