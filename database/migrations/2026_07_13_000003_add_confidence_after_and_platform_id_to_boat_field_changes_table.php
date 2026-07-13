<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_field_changes', function (Blueprint $table) {
            $table->decimal('confidence_after', 5, 4)->nullable()->after('confidence_before');
            $table->foreignId('platform_id')->nullable()->after('yacht_id')
                ->constrained('platforms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('boat_field_changes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_id');
            $table->dropColumn('confidence_after');
        });
    }
};
