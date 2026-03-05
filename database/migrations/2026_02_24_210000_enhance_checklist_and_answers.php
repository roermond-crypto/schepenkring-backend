<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'options' to boat_check (for MULTI type multiple choice values)
        Schema::table('boat_check', function (Blueprint $table) {
            $table->json('options')->nullable()->after('type');
        });

        // Add 'ai_evidence' to inspection_answers (which photo/spec AI used as proof)
        Schema::table('inspection_answers', function (Blueprint $table) {
            $table->json('ai_evidence')->nullable()->after('ai_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('boat_check', function (Blueprint $table) {
            $table->dropColumn('options');
        });

        Schema::table('inspection_answers', function (Blueprint $table) {
            $table->dropColumn('ai_evidence');
        });
    }
};
