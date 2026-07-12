<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('id')->index();
            $table->string('external_call_id')->nullable()->after('call_control_id')->unique();
            // No FK yet — the campaigns table doesn't exist until the next
            // milestone's migration, which also adds this constraint.
            $table->unsignedBigInteger('campaign_id')->nullable()->after('harbor_id')->index();
            $table->foreignId('seller_id')->nullable()->after('contact_id')->constrained('users')->nullOnDelete();
            $table->foreignId('yacht_id')->nullable()->after('seller_id')->constrained('yachts')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->after('yacht_id')->constrained('deals')->nullOnDelete();
            // Named owner_bid_id, not bid_id: the spec's "Bid" concept maps to
            // the owner_bids table (used by Deal.owner_bid_id) — the separate
            // legacy `bids`/`bidders` tables are an unrelated anonymous public
            // bid-widget flow, not what voice calls discuss.
            $table->foreignId('owner_bid_id')->nullable()->after('deal_id')->constrained('owner_bids')->nullOnDelete();
            $table->string('agent_id')->nullable()->after('owner_bid_id');
            $table->string('agent_version')->nullable()->after('agent_id');
            $table->string('model')->nullable()->after('agent_version');
            $table->string('voice')->nullable()->after('model');
            $table->string('transfer_status')->nullable()->after('outcome');
            $table->json('analysis')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
            $table->dropConstrainedForeignId('yacht_id');
            $table->dropConstrainedForeignId('deal_id');
            $table->dropConstrainedForeignId('owner_bid_id');
            $table->dropColumn([
                'provider',
                'external_call_id',
                'campaign_id',
                'agent_id',
                'agent_version',
                'model',
                'voice',
                'transfer_status',
                'analysis',
            ]);
        });
    }
};
