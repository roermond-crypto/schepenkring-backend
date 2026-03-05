<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faq_translation', function (Blueprint $table) {
            $table->string('translation_status', 20)->default('REVIEWED')->after('needs_review');
            $table->string('source_hash', 64)->nullable()->after('translation_status');
            $table->string('translated_from_hash', 64)->nullable()->after('source_hash');
            $table->boolean('is_legal')->default(false)->after('translated_from_hash');
        });
    }

    public function down(): void
    {
        Schema::table('faq_translation', function (Blueprint $table) {
            $table->dropColumn(['translation_status', 'source_hash', 'translated_from_hash', 'is_legal']);
        });
    }
};
