<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            // Check if columns exist before adding
            if (!Schema::hasColumn('faqs', 'views')) {
                $table->integer('views')->default(0);
            }
            if (!Schema::hasColumn('faqs', 'helpful')) {
                $table->integer('helpful')->default(0);
            }
            if (!Schema::hasColumn('faqs', 'not_helpful')) {
                $table->integer('not_helpful')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn(['views', 'helpful', 'not_helpful']);
        });
    }
};