<?php

namespace App\Services;

use App\Models\Yacht;

class BidRulesService
{
    public function minIncrement(): float
    {
        return (float) config('bidding.min_increment', 500);
    }

    public function minimumNextBid(Yacht $yacht): float
    {
        $minStart = (float) ($yacht->min_bid_amount ?? 0);
        $current = $yacht->current_bid;

        if ($current === null) {
            return $minStart > 0 ? $minStart : $this->minIncrement();
        }

        $next = (float) $current + $this->minIncrement();

        return $minStart > $next ? $minStart : $next;
    }
}
