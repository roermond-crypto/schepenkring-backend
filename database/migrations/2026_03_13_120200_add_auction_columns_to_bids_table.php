<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->foreignId('auction_session_id')->nullable()->after('yacht_id')->constrained('auction_sessions')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('bidder_id')->constrained('locations')->nullOnDelete();
            $table->string('status', 20)->default('leading')->after('amount');

            $table->index(['auction_session_id', 'status'], 'bids_auction_status_idx');
            $table->index(['location_id', 'created_at'], 'bids_location_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropIndex('bids_auction_status_idx');
            $table->dropIndex('bids_location_created_idx');
            $table->dropForeign(['auction_session_id']);
            $table->dropForeign(['location_id']);
            $table->dropColumn(['auction_session_id', 'location_id', 'status']);
        });
    }
};
