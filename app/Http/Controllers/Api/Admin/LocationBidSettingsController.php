<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationBidSettingsController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $location = Location::findOrFail($id);

        return response()->json([
            'location_id' => $location->id,
            'bids_page_enabled' => (bool) $location->bids_page_enabled,
            'seller_bid_notifications_enabled' => (bool) $location->seller_bid_notifications_enabled,
            'direct_buyer_seller_chat_enabled' => (bool) $location->direct_buyer_seller_chat_enabled,
            'bid_routing_mode' => $location->bid_routing_mode ?? 'direct',
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'bids_page_enabled' => 'sometimes|boolean',
            'seller_bid_notifications_enabled' => 'sometimes|boolean',
            'direct_buyer_seller_chat_enabled' => 'sometimes|boolean',
            'bid_routing_mode' => 'sometimes|string|in:direct,admin_review,broker',
        ]);

        $location = Location::findOrFail($id);
        $location->update($validated);

        return response()->json([
            'location_id' => $location->id,
            'bids_page_enabled' => (bool) $location->bids_page_enabled,
            'seller_bid_notifications_enabled' => (bool) $location->seller_bid_notifications_enabled,
            'direct_buyer_seller_chat_enabled' => (bool) $location->direct_buyer_seller_chat_enabled,
            'bid_routing_mode' => $location->bid_routing_mode ?? 'direct',
        ]);
    }
}
