<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('yacht_images', function (Blueprint $table) {
            $table->string('original_temp_url')->nullable()->after('url');
            $table->string('optimized_master_url')->nullable()->after('original_temp_url');
            $table->string('thumb_url')->nullable()->after('optimized_master_url');
            $table->string('original_kept_url')->nullable()->after('thumb_url');
            $table->string('status')->default('processing')->after('original_kept_url');
            $table->boolean('keep_original')->default(false)->after('status');
            $table->integer('quality_score')->nullable()->after('keep_original');
            $table->json('quality_flags')->nullable()->after('quality_score');
            $table->string('original_name')->nullable()->after('quality_flags');

            $table->index('status');
            $table->index(['yacht_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('yacht_images', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['yacht_id', 'status']);
            $table->dropColumn([
                'original_temp_url',
                'optimized_master_url',
                'thumb_url',
                'original_kept_url',
                'status',
                'keep_original',
                'quality_score',
                'quality_flags',
                'original_name',
            ]);
        });
    }
};
