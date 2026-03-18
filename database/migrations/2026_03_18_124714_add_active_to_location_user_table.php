<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_user', function (Blueprint $table) {
            // Allows deactivating a salesguy's access to a specific location
            // without removing the pivot row entirely (preserves history).
            $table->boolean('active')->default(true)->after('role');

            $table->index(['location_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::table('location_user', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'active']);
            $table->dropColumn('active');
        });
    }
};
