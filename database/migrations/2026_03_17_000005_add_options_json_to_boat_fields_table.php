<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_fields', function (Blueprint $table) {
            $table->json('options_json')->nullable()->after('labels_json');
        });
    }

    public function down(): void
    {
        Schema::table('boat_fields', function (Blueprint $table) {
            $table->dropColumn('options_json');
        });
    }
};
