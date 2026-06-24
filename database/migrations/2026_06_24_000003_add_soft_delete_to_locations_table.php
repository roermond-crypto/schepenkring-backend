<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->softDeletes()->after('status');
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            $table->text('delete_reason')->nullable()->after('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['deleted_by', 'delete_reason']);
        });
    }
};
