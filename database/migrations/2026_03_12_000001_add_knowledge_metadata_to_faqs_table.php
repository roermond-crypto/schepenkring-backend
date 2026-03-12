<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('language', 5)->nullable()->after('category');
            $table->string('department')->nullable()->after('language');
            $table->string('visibility')->default('internal')->after('department');
            $table->string('brand')->nullable()->after('visibility');
            $table->string('model')->nullable()->after('brand');
            $table->json('tags')->nullable()->after('model');
            $table->string('source_type')->default('faq')->after('tags');
            $table->timestamp('deprecated_at')->nullable()->after('source_type');
            $table->foreignId('superseded_by_faq_id')->nullable()->after('deprecated_at')->constrained('faqs')->nullOnDelete();
            $table->timestamp('last_indexed_at')->nullable()->after('superseded_by_faq_id');

            $table->index(['visibility', 'deprecated_at']);
            $table->index(['language', 'department']);
            $table->index(['brand', 'model']);
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropForeign(['superseded_by_faq_id']);
            $table->dropIndex(['visibility', 'deprecated_at']);
            $table->dropIndex(['language', 'department']);
            $table->dropIndex(['brand', 'model']);
            $table->dropColumn([
                'language',
                'department',
                'visibility',
                'brand',
                'model',
                'tags',
                'source_type',
                'deprecated_at',
                'superseded_by_faq_id',
                'last_indexed_at',
            ]);
        });
    }
};
