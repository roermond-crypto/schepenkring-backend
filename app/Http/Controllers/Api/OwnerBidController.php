<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\OwnerBid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerBidController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'yacht_id' => 'required|integer|exists:yachts,id',
            'amount' => 'required|numeric|min:1',
            'message' => 'nullable|string|max:2000',
        ]);

        $yacht = \App\Models\Yacht::findOrFail($validated['yacht_id']);

        $bid = OwnerBid::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'seller_id' => $yacht->user_id,
            'type' => 'offer',
            'status' => 'pending',
        ]);

        $this->notifySeller($bid);

        return response()->json($bid->load(['buyer:id,name', 'yacht:id,name']), 201);
    }

    public function counter(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::findOrFail($id);

        abort_if($bid->seller_id !== $request->user()->id, 403, 'Not your bid to counter.');
        abort_if($bid->status !== 'pending', 422, 'Bid is no longer open for counter offers.');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'message' => 'nullable|string|max:2000',
        ]);

        $bid->update(['status' => 'countered']);

        $counter = OwnerBid::create([
            'yacht_id' => $bid->yacht_id,
            'user_id' => $bid->user_id,
            'seller_id' => $bid->seller_id,
            'parent_bid_id' => $bid->id,
            'type' => 'counter',
            'amount' => $validated['amount'],
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
        ]);

        $this->notifyBuyer($counter, 'counter_offer');

        return response()->json($counter, 200);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::with(['yacht', 'buyer', 'seller'])->findOrFail($id);

        abort_if($bid->seller_id !== $request->user()->id, 403, 'Not your bid to accept.');
        abort_if($bid->status !== 'pending', 422, 'Bid is no longer open.');

        $bid->update(['status' => 'accepted']);

        $conversation = $this->openBuyerSellerChat($bid);

        $deal = Deal::create([
            'owner_bid_id' => $bid->id,
            'yacht_id' => $bid->yacht_id,
            'buyer_id' => $bid->user_id,
            'seller_id' => $bid->seller_id,
            'conversation_id' => $conversation?->id,
            'agreed_amount' => $bid->amount,
            'status' => 'active',
        ]);

        $this->notifyBuyer($bid, 'bid_accepted');

        return response()->json([
            'deal_id' => $deal->id,
            'message' => 'Bid accepted. A deal has been created and buyer notified.',
            'conversation_id' => $conversation?->id,
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $bid = OwnerBid::findOrFail($id);

        abort_if($bid->seller_id !== $request->user()->id, 403, 'Not your bid to reject.');
        abort_if($bid->status !== 'pending', 422, 'Bid is no longer open.');

        $bid->update(['status' => 'rejected']);

        $this->notifyBuyer($bid, 'bid_rejected');

        return response()->json(['message' => 'Bid rejected.']);
    }

    private function openBuyerSellerChat(OwnerBid $bid): ?Conversation
    {
        $locationId = $bid->yacht?->location_id
            ?? \App\Models\Location::query()->value('id');

        if (!$locationId) {
            return null;
        }

        return Conversation::create([
            'user_id' => $bid->seller_id,
            'location_id' => $locationId,
            'boat_id' => $bid->yacht_id,
            'status' => 'open',
            'channel' => 'bid_deal',
            'channel_origin' => 'bid_deal',
        ]);
    }

    private function notifySeller(OwnerBid $bid): void
    {
        AppNotification::notify(
            $bid->seller_id,
            'new_bid',
            'New bid received',
            "You received a bid of €{$bid->amount} on {$bid->yacht?->name}.",
            ['owner_bid_id' => $bid->id, 'yacht_id' => $bid->yacht_id],
        );
    }

    private function notifyBuyer(OwnerBid $bid, string $type): void
    {
        $messages = [
            'counter_offer' => "The seller sent a counter offer of €{$bid->amount}.",
            'bid_accepted' => "Your bid of €{$bid->amount} on {$bid->yacht?->name} was accepted!",
            'bid_rejected' => "Your bid on {$bid->yacht?->name} was declined.",
        ];

        AppNotification::notify(
            $bid->user_id,
            $type,
            ucwords(str_replace('_', ' ', $type)),
            $messages[$type] ?? '',
            ['owner_bid_id' => $bid->id, 'yacht_id' => $bid->yacht_id],
        );
    }
}
