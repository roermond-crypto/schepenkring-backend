<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->boolean('auction_enabled')->default(false)->after('allow_bidding');
            $table->string('auction_mode', 20)->nullable()->after('auction_enabled');
            $table->timestamp('auction_start')->nullable()->after('auction_mode');
            $table->timestamp('auction_end')->nullable()->after('auction_start');
            $table->unsignedInteger('auction_duration_minutes')->nullable()->after('auction_end');
            $table->unsignedInteger('auction_extension_seconds')->default(60)->after('auction_duration_minutes');

            $table->index(['auction_enabled', 'auction_mode'], 'yachts_auction_mode_idx');
            $table->index(['auction_start', 'auction_end'], 'yachts_auction_window_idx');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropIndex('yachts_auction_mode_idx');
            $table->dropIndex('yachts_auction_window_idx');
            $table->dropColumn([
                'auction_enabled',
                'auction_mode',
                'auction_start',
                'auction_end',
                'auction_duration_minutes',
                'auction_extension_seconds',
            ]);
        });
    }
};
