<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationBidSettingsController extends Controller
{
    /**
     * GET /api/me/location/bid-settings
     *
     * The authenticated user's own location's bid settings — used by the
     * sidebar to decide whether to show the "Bids" nav item for client
     * users. This endpoint never existed; the frontend has been calling
     * it since the sidebar was built, always getting a 404 and silently
     * treating that as "bids disabled," which permanently hid the Bids
     * link for every client regardless of their location's actual
     * settings. Mirrors Admin\LocationBidSettingsController::show(), just
     * scoped to the caller's own resolved location instead of an
     * admin-supplied {id}.
     */
    public function show(Request $request): JsonResponse
    {
        $location = $request->user()->resolvedLocation();

        if (! $location) {
            return response()->json([
                'location_id' => null,
                'bids_page_enabled' => false,
                'seller_bid_notifications_enabled' => false,
                'direct_buyer_seller_chat_enabled' => false,
                'bid_routing_mode' => 'direct',
            ]);
        }

        return response()->json([
            'location_id' => $location->id,
            'bids_page_enabled' => (bool) $location->bids_page_enabled,
            'seller_bid_notifications_enabled' => (bool) $location->seller_bid_notifications_enabled,
            'direct_buyer_seller_chat_enabled' => (bool) $location->direct_buyer_seller_chat_enabled,
            'bid_routing_mode' => $location->bid_routing_mode ?? 'direct',
        ]);
    }
}
