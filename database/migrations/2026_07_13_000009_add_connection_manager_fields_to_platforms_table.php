<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two small additions for the Integration Center's Connection Manager tab:
 *  - is_default: which Platform row is the "default" OpenMarine connection
 *    when more than one exists (e.g. separate feeds per brand/dealer).
 *  - feed_source_platform_id: lets a marketplace-category Platform row
 *    ("assign marketplaces to this connection") point at the openmarine
 *    connection row whose feed it consumes. Nullable/self-referencing —
 *    most rows leave this null (they are their own independent connection,
 *    per the existing architecture) and only set it when explicitly grouped
 *    under a shared connection in the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->foreignId('feed_source_platform_id')
                ->nullable()
                ->after('is_default')
                ->constrained('platforms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feed_source_platform_id');
            $table->dropColumn('is_default');
        });
    }
};
