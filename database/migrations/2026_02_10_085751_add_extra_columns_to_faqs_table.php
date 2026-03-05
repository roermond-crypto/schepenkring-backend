<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->integer('views')->default(0)->after('category');
            $table->integer('helpful')->default(0)->after('views');
            $table->integer('not_helpful')->default(0)->after('helpful');
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn(['views', 'helpful', 'not_helpful']);
        });
    }
};