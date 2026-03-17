<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained('yachts')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('highest_bidder_id')->nullable()->constrained('bidders')->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->decimal('highest_bid', 15, 2)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamp('last_bid_at')->nullable();
            $table->unsignedInteger('extension_count')->default(0);
            $table->unsignedInteger('total_bids')->default(0);
            $table->unsignedInteger('unique_bidders')->default(0);
            $table->timestamps();

            $table->index(['yacht_id', 'status'], 'auction_sessions_yacht_status_idx');
            $table->index(['status', 'start_time', 'end_time'], 'auction_sessions_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_sessions');
    }
};
