<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\KnowledgeGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LocationWidgetSettingsController extends Controller
{
    public function __construct(private KnowledgeGraphService $knowledgeGraph)
    {
    }

    public function show($id)
    {
        $location = Location::findOrFail($id);

        return response()->json([
            'enabled' => $location->chat_widget_enabled,
            'welcome_text' => $location->chat_widget_welcome_text,
            'theme' => $location->chat_widget_theme,
            'bids_page_enabled' => $location->bids_page_enabled ?? true,
            'seller_bid_notifications_enabled' => $location->seller_bid_notifications_enabled ?? true,
            'direct_buyer_seller_chat_enabled' => $location->direct_buyer_seller_chat_enabled ?? false,
            'bid_routing_mode' => $location->bid_routing_mode ?? 'direct',
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'enabled' => 'sometimes|boolean',
            'welcome_text' => 'nullable|string|max:1000',
            'theme' => 'nullable|string|in:ocean,violet,sunset',
            'bids_page_enabled' => 'sometimes|boolean',
            'seller_bid_notifications_enabled' => 'sometimes|boolean',
            'direct_buyer_seller_chat_enabled' => 'sometimes|boolean',
            'bid_routing_mode' => 'sometimes|string|in:direct,broker',
        ]);

        $location = Location::findOrFail($id);
        $location->update(array_filter([
            'chat_widget_enabled' => $request->enabled,
            'chat_widget_welcome_text' => $request->welcome_text,
            'chat_widget_theme' => $request->theme ?? $location->chat_widget_theme,
            'bids_page_enabled' => $request->has('bids_page_enabled') ? $request->bids_page_enabled : null,
            'seller_bid_notifications_enabled' => $request->has('seller_bid_notifications_enabled') ? $request->seller_bid_notifications_enabled : null,
            'direct_buyer_seller_chat_enabled' => $request->has('direct_buyer_seller_chat_enabled') ? $request->direct_buyer_seller_chat_enabled : null,
            'bid_routing_mode' => $request->bid_routing_mode,
        ], fn($v) => $v !== null));

        try {
            $this->knowledgeGraph->syncLocation($location->fresh());
        } catch (\Throwable $e) {
            Log::warning('Location knowledge sync failed after widget settings update', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Widget settings updated successfully',
            'location' => $location,
        ]);
    }
}
