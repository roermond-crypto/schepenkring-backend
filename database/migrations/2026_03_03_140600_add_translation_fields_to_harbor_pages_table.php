<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('harbor_pages', function (Blueprint $table) {
            $table->string('translation_status', 20)->default('AI_DRAFT')->after('source_data_hash');
            $table->string('translated_from_hash', 64)->nullable()->after('translation_status');
        });
    }

    public function down(): void
    {
        Schema::table('harbor_pages', function (Blueprint $table) {
            $table->dropColumn(['translation_status', 'translated_from_hash']);
        });
    }
};
