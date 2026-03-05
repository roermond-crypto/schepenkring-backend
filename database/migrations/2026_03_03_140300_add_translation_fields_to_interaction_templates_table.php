<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interaction_templates', function (Blueprint $table) {
            $table->string('source_locale', 5)->default('nl')->after('name');
            $table->string('source_hash', 64)->nullable()->after('source_locale');
        });
    }

    public function down(): void
    {
        Schema::table('interaction_templates', function (Blueprint $table) {
            $table->dropColumn(['source_locale', 'source_hash']);
        });
    }
};
