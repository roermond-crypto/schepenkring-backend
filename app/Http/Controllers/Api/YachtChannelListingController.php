<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Models\YachtChannelListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YachtChannelListingController extends Controller
{
    // GET /yachts/{id}/channel-listings
    public function index(Request $request, int $id): JsonResponse
    {
        $yacht = Yacht::findOrFail($id);

        $this->authorizeYachtAccess($request, $yacht);

        $listings = YachtChannelListing::where('yacht_id', $yacht->id)->get();

        return response()->json($listings->map(fn (YachtChannelListing $l) => $this->serialize($l)));
    }

    // PUT /yachts/{id}/channel-listings/marktplaats
    public function upsertMarktplaats(Request $request, int $id): JsonResponse
    {
        $yacht = Yacht::findOrFail($id);

        $this->authorizeYachtAccess($request, $yacht);

        $validated = $request->validate([
            'is_enabled'  => 'boolean',
            'auto_publish' => 'boolean',
            'settings_json' => 'nullable|array',
            'settings_json.marktplaats_promoted'    => 'nullable|boolean',
            'settings_json.marktplaats_budget_type' => 'nullable|string|in:cpc,cpm,fixed',
            'settings_json.marktplaats_cpc_bid'     => 'nullable|numeric|min:0',
            'settings_json.marktplaats_target_views' => 'nullable|integer|min:0',
        ]);

        $listing = YachtChannelListing::updateOrCreate(
            ['yacht_id' => $yacht->id, 'channel_name' => 'marktplaats'],
            array_merge($validated, ['status' => $validated['is_enabled'] ?? false ? 'active' : 'paused'])
        );

        return response()->json($this->serialize($listing));
    }

    // POST /yachts/{id}/channel-listings/marktplaats/{action}
    public function actionMarktplaats(Request $request, int $id, string $action): JsonResponse
    {
        $yacht = Yacht::findOrFail($id);

        $this->authorizeYachtAccess($request, $yacht);

        if (!in_array($action, ['retry', 'pause', 'remove', 'sync'])) {
            return response()->json(['message' => 'Unknown action.'], 422);
        }

        $listing = YachtChannelListing::firstOrCreate(
            ['yacht_id' => $yacht->id, 'channel_name' => 'marktplaats'],
            ['is_enabled' => false, 'status' => 'paused']
        );

        match ($action) {
            'pause'  => $listing->update(['is_enabled' => false, 'status' => 'paused']),
            'remove' => $listing->update(['is_enabled' => false, 'status' => 'removed', 'last_error' => null]),
            'retry', 'sync' => $listing->update(['status' => 'pending', 'last_error' => null, 'last_synced_at' => now()]),
        };

        return response()->json(['message' => "Action '{$action}' queued.", 'listing' => $this->serialize($listing->fresh())]);
    }

    private function serialize(YachtChannelListing $l): array
    {
        return [
            'id'            => $l->id,
            'yacht_id'      => $l->yacht_id,
            'channel_name'  => $l->channel_name,
            'is_enabled'    => $l->is_enabled,
            'auto_publish'  => $l->auto_publish,
            'status'        => $l->status,
            'settings_json' => $l->settings_json ?? [],
            'capabilities'  => $l->capabilities,
            'last_synced_at' => $l->last_synced_at?->toIso8601String(),
            'last_error'    => $l->last_error,
            'created_at'    => $l->created_at?->toIso8601String(),
            'updated_at'    => $l->updated_at?->toIso8601String(),
        ];
    }

    private function authorizeYachtAccess(Request $request, Yacht $yacht): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthorized.');
        }
        // Admins and employees can access any yacht; owners only their own
        if (in_array($user->role ?? $user->type ?? '', ['admin', 'employee'])) {
            return;
        }
        if ($yacht->user_id !== $user->id) {
            abort(403, 'Forbidden.');
        }
    }
}
