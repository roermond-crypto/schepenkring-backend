<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            // Yachts created by the Integration Center's Test Yacht Generator
            // — used for mapping/export previews and regression testing,
            // never eligible for a real marketplace publish. See
            // OpenMarineService::validate() (test yachts always fail
            // validation with an explicit reason), PlatformExportToolsService
            // ::resolveYacht() (excluded from the auto-picked sample), and
            // YachtShiftSyncService::export() (excluded from the real export
            // batch query).
            $table->boolean('is_test')->default(false)->after('status');
            $table->index('is_test');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropIndex(['is_test']);
            $table->dropColumn('is_test');
        });
    }
};
