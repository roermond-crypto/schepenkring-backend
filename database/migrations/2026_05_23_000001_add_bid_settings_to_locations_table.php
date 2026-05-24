<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('bids_page_enabled')->default(true)->after('chat_widget_theme');
            $table->boolean('seller_bid_notifications_enabled')->default(true)->after('bids_page_enabled');
            $table->boolean('direct_buyer_seller_chat_enabled')->default(false)->after('seller_bid_notifications_enabled');
            $table->string('bid_routing_mode')->default('direct')->after('direct_buyer_seller_chat_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'bids_page_enabled',
                'seller_bid_notifications_enabled',
                'direct_buyer_seller_chat_enabled',
                'bid_routing_mode',
            ]);
        });
    }
};
