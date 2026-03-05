<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->string('source_locale', 5)->default('nl')->after('slug');
            $table->string('source_hash', 64)->nullable()->after('source_locale');
            $table->string('meta_title')->nullable()->after('excerpt');
            $table->string('meta_description')->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn(['source_locale', 'source_hash', 'meta_title', 'meta_description']);
        });
    }
};
