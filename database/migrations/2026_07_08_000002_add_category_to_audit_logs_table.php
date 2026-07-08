<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Coarse grouping for filtering (scraper, pinecone, video, ai,
            // yachtshift, ...) distinct from the fine-grained `action` string,
            // so e.g. "show me everything from today's scrape run" doesn't
            // require matching every individual action name.
            $table->string('category')->nullable()->after('action');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
