<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_question_templates', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_question_templates', function (Blueprint $table) {
            $table->dropColumn('translations');
        });
    }
};
