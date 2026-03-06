<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('data')->constrained('locations')->nullOnDelete();
            $table->softDeletes();
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['location_id']);
            $table->dropConstrainedForeignId('location_id');
            $table->dropSoftDeletes();
        });
    }
};
