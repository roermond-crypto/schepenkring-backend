<?php

namespace App\Actions\Bids;

use App\Models\Yacht;
use App\Services\AuctionService;

class GetBidStateAction
{
    public function __construct(private AuctionService $auctions)
    {
    }

    public function execute(Yacht $yacht, ?int $locationId = null): array
    {
        return $this->auctions->publicState($yacht, $locationId);
    }
}
