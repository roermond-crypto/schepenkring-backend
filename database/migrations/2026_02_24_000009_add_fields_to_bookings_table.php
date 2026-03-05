<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('yacht_id');
            $table->foreignId('seller_user_id')->nullable()->constrained('users')->nullOnDelete()->after('user_id');
            $table->foreignId('bid_id')->nullable()->constrained('bids')->nullOnDelete()->after('seller_user_id');
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete()->after('bid_id');
            $table->string('location')->nullable()->after('end_at');
            $table->string('type')->default('appointment')->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['location', 'type']);
            $table->dropConstrainedForeignId('deal_id');
            $table->dropConstrainedForeignId('bid_id');
            $table->dropConstrainedForeignId('seller_user_id');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
