<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations', 'bids_page_enabled')) {
                $table->boolean('bids_page_enabled')->default(false)->after('chat_widget_theme');
            }
            if (!Schema::hasColumn('locations', 'seller_bid_notifications_enabled')) {
                $table->boolean('seller_bid_notifications_enabled')->default(true)->after('bids_page_enabled');
            }
            if (!Schema::hasColumn('locations', 'direct_buyer_seller_chat_enabled')) {
                $table->boolean('direct_buyer_seller_chat_enabled')->default(false)->after('seller_bid_notifications_enabled');
            }
            // direct | admin_review | broker
            if (!Schema::hasColumn('locations', 'bid_routing_mode')) {
                $table->string('bid_routing_mode')->default('direct')->after('direct_buyer_seller_chat_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $columns = array_filter(
                ['bids_page_enabled', 'seller_bid_notifications_enabled', 'direct_buyer_seller_chat_enabled', 'bid_routing_mode'],
                fn($col) => Schema::hasColumn('locations', $col)
            );
            if ($columns) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
