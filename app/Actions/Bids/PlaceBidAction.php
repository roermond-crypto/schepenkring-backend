<?php

namespace App\Actions\Bids;

use App\Enums\RiskLevel;
use App\Models\Bid;
use App\Models\Bidder;
use App\Models\Yacht;
use App\Services\ActionSecurity;
use App\Services\BidRulesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceBidAction
{
    public function __construct(
        private BidRulesService $rules,
        private ActionSecurity $security
    ) {
    }

    public function execute(Bidder $bidder, Yacht $yacht, float $amount, Request $request): Bid
    {
        $bid = DB::transaction(function () use ($bidder, $yacht, $amount, $request) {
            $locked = Yacht::query()->whereKey($yacht->id)->lockForUpdate()->first();
            if (! $locked) {
                throw ValidationException::withMessages([
                    'yacht_id' => 'Listing not found.',
                ]);
            }

            if (! $locked->allow_bidding) {
                throw ValidationException::withMessages([
                    'yacht_id' => 'Bidding is not enabled for this listing.',
                ]);
            }

            $minimum = $this->rules->minimumNextBid($locked);
            if ($amount < $minimum) {
                throw ValidationException::withMessages([
                    'amount' => 'Bid must be at least ' . number_format($minimum, 2, '.', ''),
                ]);
            }

            $bid = Bid::create([
                'yacht_id' => $locked->id,
                'bidder_id' => $bidder->id,
                'amount' => $amount,
                'bidder_name' => $bidder->full_name,
                'bidder_email' => $bidder->email,
                'bidder_phone' => $bidder->phone,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $locked->current_bid = $amount;
            $locked->save();

            return $bid;
        });

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
