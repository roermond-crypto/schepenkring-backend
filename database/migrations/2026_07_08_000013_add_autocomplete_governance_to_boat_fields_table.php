<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_fields', function (Blueprint $table) {
            $table->boolean('enable_autocomplete')->default(false)->after('never_export');
            // 'freetext' (no governance, plain input) | 'fixed' (options_json dropdown,
            // already supported) | 'database' (backed by catalog_values) | 'ai' (AI
            // suggestions surfaced alongside database values).
            $table->string('value_source', 20)->default('freetext')->after('enable_autocomplete');
            $table->boolean('allow_new_values')->default(true)->after('value_source');
            $table->boolean('allow_inline_archive')->default(true)->after('allow_new_values');
            $table->boolean('fuzzy_matching')->default(true)->after('allow_inline_archive');
            $table->boolean('is_required')->default(false)->after('fuzzy_matching');
            $table->boolean('is_searchable')->default(true)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('boat_fields', function (Blueprint $table) {
            $table->dropColumn([
                'enable_autocomplete',
                'value_source',
                'allow_new_values',
                'allow_inline_archive',
                'fuzzy_matching',
                'is_required',
                'is_searchable',
            ]);
        });
    }
};
