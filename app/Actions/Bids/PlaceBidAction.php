<?php

namespace App\Actions\Bids;

use App\Enums\RiskLevel;
use App\Models\Bidder;
use App\Models\Yacht;
use App\Services\ActionSecurity;
use App\Services\AuctionService;
use Illuminate\Http\Request;

class PlaceBidAction
{
    public function __construct(
        private AuctionService $auctions,
        private ActionSecurity $security
    ) {
    }

    public function execute(Bidder $bidder, Yacht $yacht, float $amount, Request $request, ?int $locationId = null): \App\Models\Bid
    {
        $bid = $this->auctions->placeBid($bidder, $yacht, $amount, $request, $locationId);

        $this->security->log('bid.created', RiskLevel::LOW, null, $bid, [
            'yacht_id' => $yacht->id,
        ], [
            'entity_type' => $bid::class,
            'entity_id' => $bid->id,
            'snapshot_after' => $bid->toArray(),
        ]);

        return $bid;
    }
}
