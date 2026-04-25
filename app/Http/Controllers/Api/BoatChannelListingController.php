<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoatChannelListing;
use App\Models\BoatChannelLog;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoatChannelListingController extends Controller
{
    public function index(Request $request, string $id): JsonResponse
    {
        $boat = Yacht::with(['channelListings.logs'])->findOrFail($id);
        $this->authorizeAccess($request, $boat);

        return response()->json(
            $boat->channelListings
                ->map(fn (BoatChannelListing $listing) => $this->serializeListing($listing))
                ->values()
        );
    }

    public function update(Request $request, string $id, string $channel): JsonResponse
    {
        $boat = Yacht::findOrFail($id);
        $this->authorizeAccess($request, $boat);

        $validated = $request->validate([
            'is_enabled' => 'sometimes|boolean',
            'auto_publish' => 'sometimes|boolean',
            'settings_json' => 'sometimes|array',
            'settings_json.marktplaats_promoted' => 'sometimes|boolean',
            'settings_json.marktplaats_budget_type' => 'nullable|string|max:50',
            'settings_json.marktplaats_cpc_bid' => 'nullable|numeric|min:0',
            'settings_json.marktplaats_target_views' => 'nullable|integer|min:0',
        ]);

        $listing = BoatChannelListing::firstOrCreate(
            ['boat_id' => $boat->id, 'channel_name' => $channel],
            ['status' => 'draft'],
        );

        $listing->fill([
            'is_enabled' => $validated['is_enabled'] ?? $listing->is_enabled,
            'auto_publish' => $validated['auto_publish'] ?? $listing->auto_publish,
            'settings_json' => array_merge($listing->settings_json ?? [], $validated['settings_json'] ?? []),
        ]);

        if ($listing->is_enabled) {
            $listing->status = $listing->auto_publish ? 'queued' : 'ready';
            $listing->external_id = $listing->external_id ?: $this->feedReference($boat, $channel);
            $listing->last_sync_at = now();
        } else {
            $listing->status = 'draft';
        }

        $listing->save();
        $this->log($listing, 'settings_update', 'success', ['settings_json' => $listing->settings_json]);

        return response()->json($this->serializeListing($listing->fresh()));
    }

    public function logs(Request $request, string $id, string $channel): JsonResponse
    {
        $boat = Yacht::findOrFail($id);
        $this->authorizeAccess($request, $boat);

        $listing = BoatChannelListing::where('boat_id', $boat->id)
            ->where('channel_name', $channel)
            ->firstOrFail();

        return response()->json($listing->logs()->limit(50)->get());
    }

    public function retry(Request $request, string $id, string $channel): JsonResponse
    {
        return $this->mutateListing($request, $id, $channel, 'retry', [
            'status' => 'queued',
            'last_sync_at' => now(),
        ]);
    }

    public function pause(Request $request, string $id, string $channel): JsonResponse
    {
        return $this->mutateListing($request, $id, $channel, 'pause', [
            'status' => 'paused',
            'last_sync_at' => now(),
        ]);
    }

    public function remove(Request $request, string $id, string $channel): JsonResponse
    {
        return $this->mutateListing($request, $id, $channel, 'remove', [
            'is_enabled' => false,
            'status' => 'removed',
            'removed_at' => now(),
            'last_sync_at' => now(),
        ]);
    }

    public function sync(Request $request, string $id, string $channel): JsonResponse
    {
        return $this->mutateListing($request, $id, $channel, 'sync', [
            'last_sync_at' => now(),
        ]);
    }

    private function mutateListing(Request $request, string $id, string $channel, string $action, array $attributes): JsonResponse
    {
        $boat = Yacht::findOrFail($id);
        $this->authorizeAccess($request, $boat);

        $listing = BoatChannelListing::firstOrCreate(
            ['boat_id' => $boat->id, 'channel_name' => $channel],
            ['status' => 'draft'],
        );

        if (! $listing->external_id && $channel === 'marktplaats') {
            $listing->external_id = $this->feedReference($boat, $channel);
        }

        if ($action === 'sync' && ! isset($attributes['status'])) {
            $attributes['status'] = $listing->is_enabled ? 'ready' : $listing->status;
        }

        $listing->fill($attributes);
        $listing->save();
        $this->log($listing, $action, 'success');

        return response()->json($this->serializeListing($listing->fresh()));
    }

    private function serializeListing(?BoatChannelListing $listing): array
    {
        if (! $listing) {
            return [];
        }

        return array_merge($listing->toArray(), [
            'capabilities' => [
                'supports_promotion' => true,
                'supports_cpc' => true,
                'feed_url' => $listing->external_url,
            ],
        ]);
    }

    private function log(BoatChannelListing $listing, string $action, string $status, array $requestPayload = []): void
    {
        BoatChannelLog::create([
            'boat_id' => $listing->boat_id,
            'boat_channel_listing_id' => $listing->id,
            'channel_name' => $listing->channel_name,
            'action' => $action,
            'status' => $status,
            'request_payload_json' => $requestPayload,
            'response_payload_json' => [
                'listing_status' => $listing->status,
            ],
        ]);
    }

    private function feedReference(Yacht $boat, string $channel): string
    {
        return $channel . '-' . ($boat->vessel_id ?: $boat->id);
    }

    private function authorizeAccess(Request $request, Yacht $boat): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthorized');
        }

        if (strtolower((string) $user->role) === 'admin') {
            return;
        }

        if ($boat->user_id !== $user->id && $boat->location_id !== $user->id) {
            abort(403, 'Forbidden');
        }
    }
}
