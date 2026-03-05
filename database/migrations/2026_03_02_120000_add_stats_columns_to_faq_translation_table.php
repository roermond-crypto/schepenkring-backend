<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faq_translation', function (Blueprint $table) {
            if (!Schema::hasColumn('faq_translation', 'views')) {
                $table->unsignedBigInteger('views')->default(0)->after('answer');
            }
            if (!Schema::hasColumn('faq_translation', 'helpful')) {
                $table->unsignedBigInteger('helpful')->default(0)->after('views');
            }
            if (!Schema::hasColumn('faq_translation', 'not_helpful')) {
                $table->unsignedBigInteger('not_helpful')->default(0)->after('helpful');
            }
        });
    }

    public function down(): void
    {
        Schema::table('faq_translation', function (Blueprint $table) {
            $table->dropColumn(['views', 'helpful', 'not_helpful']);
        });
    }
};
