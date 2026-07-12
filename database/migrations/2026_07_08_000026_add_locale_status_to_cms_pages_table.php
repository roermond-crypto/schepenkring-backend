<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            // Per-language workflow state, independent of the page-level
            // `status` column: {nl: 'published', en: 'needs_review', ...}.
            // Values: missing | draft | needs_review | approved | published.
            $table->json('locale_status')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropColumn('locale_status');
        });
    }
};
