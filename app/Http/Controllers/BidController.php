<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use App\Services\SystemLogService;
use App\Events\BidAccepted;

class BidController extends Controller
{
    public function placeBid(Request $request)
    {
        $request->validate([
            'yacht_id' => 'required|exists:yachts,id',
            'amount'   => 'required|numeric|min:1',
        ]);

        $yacht = Yacht::findOrFail($request->yacht_id);

        // 1. Check if the vessel is already sold
        if ($yacht->status === 'Sold') {
            return response()->json(['message' => 'Bidding is closed. Vessel is sold.'], 403);
        }

        // 2. Allow bidding on both "For Bid" and "For Sale" items
        if (!in_array($yacht->status, ['For Bid', 'For Sale'])) {
            return response()->json(['message' => 'This vessel is not currently open for offers.'], 403);
        }

        // 3. Ensure the new bid is higher than the current highest bid
        if ($yacht->current_bid !== null && $request->amount <= $yacht->current_bid) {
            return response()->json([
                'message' => 'Bid must be higher than the current offer: €' . number_format($yacht->current_bid)
            ], 422);
        }

        return DB::transaction(function () use ($request, $yacht) {
            // 4. Mark previous active bids as 'outbid'
            Bid::where('yacht_id', $yacht->id)
                ->where('status', 'active')
                ->update(['status' => 'outbid']);

            // 5. Create the new bid using the authenticated user
            $bid = Bid::create([
                'yacht_id' => $yacht->id,
                'user_id'  => auth()->id(),
                'amount'   => $request->amount,
                'status'   => 'active'
            ]);

            // 6. Update the main Yacht record with the new high bid
            $yacht->update([
                'current_bid' => $request->amount,
                'status'      => 'For Bid'
            ]);

            // Log the bid creation
            SystemLogService::logBidCreation($bid, $request);

            return response()->json([
                'message' => 'Bid placed successfully.',
                'bid'     => $bid->load('user')
            ], 201);
        });
    }

    public function history($yachtId)
    {
        $history = Bid::with('user:id,name')
            ->where('yacht_id', $yachtId)
            ->orderBy('amount', 'desc')
            ->get();

        $highest = $history->first();

        return response()->json([
            'bids' => $history,
            'highestBid' => $highest ? [
                'amount' => (float) $highest->amount,
                'bid_id' => $highest->id,
                'created_at' => $highest->created_at,
            ] : null,
        ]);
    }

    /**
     * Accept a bid - Marks the yacht as Sold and closes all other bids.
     */
    public function acceptBid(Request $request, $id)
    {
        $bid = Bid::findOrFail($id);
        $yacht = $bid->yacht;

        return DB::transaction(function () use ($bid, $yacht, $request) {
            // 1. Mark this bid as 'won'
            $bid->update([
                'status' => 'won',
                'finalized_at' => Carbon::now(),
                'finalized_by' => auth()->id() 
            ]);

            // 2. Mark all other active/outbid bids as 'cancelled'
            Bid::where('yacht_id', $yacht->id)
                ->where('id', '!=', $bid->id)
                ->update(['status' => 'cancelled']);

            // 3. Update Yacht status to Sold
            $yacht->update(['status' => 'Sold']);

            // Log the bid acceptance
            SystemLogService::logBidAccepted($bid, $request);

            event(new BidAccepted($bid, $request->user()));

            return response()->json(['message' => 'Bid accepted. Vessel marked as Sold.']);
        });
    }

    /**
     * Decline a bid - Useful for clearing out low-ball offers.
     */
    public function declineBid($id)
    {
        $bid = Bid::findOrFail($id);
        
        $bid->update([
            'status' => 'cancelled',
            'finalized_at' => Carbon::now(),
            'finalized_by' => auth()->id()
        ]);

        // Log the bid decline
        SystemLogService::logBidDeclined($bid, request());

        return response()->json(['message' => 'Bid declined.']);
    }
    /**
     * Get bids placed BY the authenticated user (bidder dashboard).
     * Returns yacht info with slug for deep links.
     */
    public function myBids(Request $request)
    {
        try {
            $user = auth()->user();

            $bids = Bid::with(['yacht'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $bids->transform(function ($bid) {
                $yacht = $bid->yacht;
                if (!$yacht) {
                    $bid->yacht = null;
                    return $bid;
                }

                $slug = \Illuminate\Support\Str::slug($yacht->boat_name ?? 'yacht');

                $bid->yacht = [
                    'id'          => $yacht->id,
                    'boat_name'   => $yacht->boat_name ?? 'Onbekend',
                    'vessel_id'   => $yacht->vessel_id ?? $yacht->id,
                    'main_image'  => $yacht->main_image ?? null,
                    'status'      => $yacht->status ?? 'Unknown',
                    'current_bid' => $yacht->current_bid,
                    'price'       => $yacht->price ?? 0,
                    'slug'        => $slug,
                ];
                return $bid;
            });

            return response()->json($bids);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get bids on the seller's yachts (only bids >= min_bid_amount).
     */
    public function sellerBids(Request $request)
    {
        try {
            $user = auth()->user();
            $yachts = Yacht::where('user_id', $user->id)->get();
            $yachtIds = $yachts->pluck('id');
            $minBidMap = $yachts->pluck('min_bid_amount', 'id');

            // Get all bids on seller's yachts
            $bids = Bid::with(['user:id,name,email', 'yacht'])
                ->whereIn('yacht_id', $yachtIds)
                ->orderBy('created_at', 'desc')
                ->get();

            // Filter out bids below min_bid_amount
            $bids = $bids->filter(function ($bid) use ($minBidMap) {
                $minBid = $minBidMap[$bid->yacht_id] ?? 0;
                return $minBid <= 0 || $bid->amount >= $minBid;
            })->values();

            $bids->transform(function ($bid) {
                $yacht = $bid->yacht;
                if (!$yacht) {
                    $bid->yacht = null;
                    return $bid;
                }

                $bid->yacht = [
                    'id'            => $yacht->id,
                    'boat_name'     => $yacht->boat_name ?? 'Onbekend',
                    'vessel_id'     => $yacht->vessel_id ?? $yacht->id,
                    'main_image'    => $yacht->main_image ?? null,
                    'status'        => $yacht->status ?? 'Unknown',
                    'current_bid'   => $yacht->current_bid,
                    'price'         => $yacht->price ?? 0,
                    'min_bid_amount'=> $yacht->min_bid_amount ?? 0,
                ];
                return $bid;
            });

            return response()->json($bids);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Accept a bid as the yacht owner (no special permission needed).
     */
    public function acceptBidAsOwner(Request $request, $id)
    {
        $bid = Bid::findOrFail($id);
        $yacht = $bid->yacht;

        // Verify the authenticated user owns this yacht
        if ($yacht->user_id !== auth()->id()) {
            return response()->json(['message' => 'You do not own this vessel.'], 403);
        }

        return $this->acceptBid($request, $id);
    }

    /**
     * Decline a bid as the yacht owner (no special permission needed).
     */
    public function declineBidAsOwner($id)
    {
        $bid = Bid::findOrFail($id);
        $yacht = $bid->yacht;

        // Verify the authenticated user owns this yacht
        if ($yacht->user_id !== auth()->id()) {
            return response()->json(['message' => 'You do not own this vessel.'], 403);
        }

        return $this->declineBid($id);
    }

public function index(Request $request)
{
    try {
        // Get the authenticated user
        $user = auth()->user();

        // If the user is a partner/seller, restrict to bids on their yachts
        if ($user->hasRole('partner') || $user->hasRole('Seller')) {
            $yachts = Yacht::where('user_id', $user->id)->get();
            $yachtIds = $yachts->pluck('id');
            $minBidMap = $yachts->pluck('min_bid_amount', 'id');
            
            $bids = Bid::with(['user:id,name,email', 'yacht'])
                ->whereIn('yacht_id', $yachtIds)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            // Filter out bids below min_bid_amount
            $filtered = $bids->getCollection()->filter(function ($bid) use ($minBidMap) {
                $minBid = $minBidMap[$bid->yacht_id] ?? 0;
                return $minBid <= 0 || $bid->amount >= $minBid;
            })->values();
            $bids->setCollection($filtered);
        } else {
            // Admin/Employee can see all bids
            $bids = Bid::with(['user:id,name,email', 'yacht'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }

        // Transform yacht data to avoid missing column errors
        $bids->getCollection()->transform(function ($bid) {
            $yacht = $bid->yacht;
            
            if (!$yacht) {
                $bid->yacht = null;
                return $bid;
            }

            $bid->yacht = [
                'id'            => $yacht->id,
                'boat_name'     => $yacht->boat_name ?? $yacht->name ?? 'Onbekend',
                'vessel_id'     => $yacht->vessel_id ?? $yacht->id,
                'main_image'    => $yacht->main_image ?? $yacht->main_image_url ?? null,
                'status'        => $yacht->status ?? 'Unknown',
                'current_bid'   => $yacht->current_bid,
                'price'         => $yacht->price ?? 0,
                'min_bid_amount'=> $yacht->min_bid_amount ?? 0,
            ];
            return $bid;
        });

        return response()->json($bids);

    } catch (\Exception $e) {
        return response()->json([
            'error'   => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString()
        ], 500);
    }
}

public function destroy($id)
{
    $bid = Bid::findOrFail($id);
    $bid->delete();

    // Optional: log the deletion
    SystemLogService::logBidDeletion($bid, request());

    return response()->json(['message' => 'Bid deleted successfully.']);
}

}
