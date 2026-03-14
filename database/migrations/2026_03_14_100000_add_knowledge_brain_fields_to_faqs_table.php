<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->unsignedSmallInteger('ai_score')->nullable()->after('not_helpful');
            $table->timestamp('last_reviewed_at')->nullable()->after('ai_score');
            $table->boolean('needs_update')->default(false)->after('last_reviewed_at');
            $table->text('ai_review_summary')->nullable()->after('needs_update');
            $table->text('ai_suggested_answer')->nullable()->after('ai_review_summary');

            $table->index(['needs_update', 'last_reviewed_at']);
            $table->index(['ai_score', 'needs_update']);
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropIndex(['needs_update', 'last_reviewed_at']);
            $table->dropIndex(['ai_score', 'needs_update']);
            $table->dropColumn([
                'ai_score',
                'last_reviewed_at',
                'needs_update',
                'ai_review_summary',
                'ai_suggested_answer',
            ]);
        });
    }
};
