<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatPlatformPublication;
use App\Models\Platform;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoatPlatformPublicationController extends Controller
{
    /**
     * GET /admin/yachts/{yacht}/platform-publications
     *
     * Returns a flat array of BoatPlatformPublication records, one per active platform.
     * Auto-creates missing rows (enabled=true) so the UI always sees a full list.
     */
    public function index(Yacht $yacht): JsonResponse
    {
        $platforms = Platform::where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $existing = BoatPlatformPublication::where('yacht_id', $yacht->id)
            ->whereIn('platform_id', $platforms->pluck('id'))
            ->get()
            ->keyBy('platform_id');

        $publications = $platforms->map(function (Platform $platform) use ($yacht, $existing) {
            $pub = $existing->get($platform->id);

            if (! $pub) {
                // Auto-create: default ON for every active platform
                $pub = BoatPlatformPublication::create([
                    'yacht_id'    => $yacht->id,
                    'platform_id' => $platform->id,
                    'enabled'     => true,
                    'status'      => 'pending',
                ]);
            }

            return $pub;
        });

        return response()->json($publications->values());
    }

    /**
     * PUT /admin/yachts/{yacht}/platform-publications
     *
     * Body: [{ platform_id, enabled, external_platform_id }, ...]
     */
    public function update(Request $request, Yacht $yacht): JsonResponse
    {
        $items = $request->validate([
            '*'                          => 'array',
            '*.platform_id'              => 'required|integer|exists:platforms,id',
            '*.enabled'                  => 'required|boolean',
            '*.external_platform_id'     => 'nullable|string|max:255',
        ]);

        $updated = [];

        foreach ($items as $item) {
            $pub = BoatPlatformPublication::updateOrCreate(
                [
                    'yacht_id'    => $yacht->id,
                    'platform_id' => $item['platform_id'],
                ],
                [
                    'enabled'              => $item['enabled'],
                    'external_platform_id' => $item['external_platform_id'] ?? null,
                ]
            );

            $updated[] = $pub->load('platform');
        }

        return response()->json($updated);
    }

    /**
     * POST /admin/yachts/{yacht}/platform-publications/{platform}/sync
     *
     * Triggers a sync for a single platform. Currently logs intent and marks
     * last_sync_at; actual push logic lives in platform-specific jobs.
     */
    public function sync(Request $request, Yacht $yacht, Platform $platform): JsonResponse
    {
        $pub = BoatPlatformPublication::firstOrCreate(
            ['yacht_id' => $yacht->id, 'platform_id' => $platform->id],
            ['enabled' => true, 'status' => 'pending']
        );

        if (! $pub->enabled) {
            return response()->json(['message' => 'Platform is disabled for this boat'], 422);
        }

        $pub->update([
            'last_sync_at' => now(),
            'status'       => 'synced',
            'retry_count'  => 0,
        ]);

        Log::info('[PlatformSync] Manual sync triggered', [
            'yacht_id'    => $yacht->id,
            'platform_id' => $platform->id,
            'platform'    => $platform->name,
        ]);

        return response()->json([
            'message'     => 'Sync triggered for ' . $platform->name,
            'publication' => $pub->load('platform'),
        ]);
    }
}
