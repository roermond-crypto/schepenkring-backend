<?php

namespace App\Actions\Bids;

use App\Models\Yacht;
use App\Services\BidRulesService;

class GetBidStateAction
{
    public function __construct(private BidRulesService $rules)
    {
    }

    public function execute(Yacht $yacht): array
    {
        return [
            'yacht_id' => $yacht->id,
            'allow_bidding' => (bool) $yacht->allow_bidding,
            'current_bid' => $yacht->current_bid !== null ? (float) $yacht->current_bid : null,
            'minimum_next_bid' => $this->rules->minimumNextBid($yacht),
            'min_increment' => $this->rules->minIncrement(),
        ];
    }
}
