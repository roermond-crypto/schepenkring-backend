<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Test yachts back the mapping editor's "Test now" and the marketplace
 * preview pipeline with realistic, disposable data — always is_test=true,
 * so OpenMarineService::validate() always rejects them for a real publish,
 * PlatformExportToolsService::resolveYacht() never auto-picks one as a
 * generic sample, and YachtShiftSyncService::export() never includes one
 * in a real export batch.
 */
class TestYachtController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'boat_type' => 'nullable|string|max:50',
            'builder' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'engine' => 'nullable|string|max:255',
            'length' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        $overrides = array_filter([
            'boat_type' => $data['boat_type'] ?? null,
            'manufacturer' => $data['builder'] ?? null,
            'model' => $data['model'] ?? null,
            'engine_manufacturer' => $data['engine'] ?? null,
            'loa' => $data['length'] ?? null,
            'price' => $data['price'] ?? null,
        ], fn ($v) => $v !== null);

        $overrides['user_id'] = $request->user()?->id;

        $yacht = Yacht::factory()->create($overrides);

        return response()->json($yacht, 201);
    }

    public function index(): JsonResponse
    {
        $yachts = Yacht::where('is_test', true)
            ->latest()
            ->get(['id', 'boat_name', 'boat_type', 'manufacturer', 'model', 'price', 'created_at']);

        return response()->json(['data' => $yachts]);
    }

    public function destroy(Yacht $yacht): JsonResponse
    {
        if (! $yacht->is_test) {
            abort(422, 'Only test yachts can be deleted through this endpoint.');
        }

        $yacht->delete();

        return response()->json(['message' => 'Test yacht deleted']);
    }
}
