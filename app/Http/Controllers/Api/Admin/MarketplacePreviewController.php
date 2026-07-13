<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Models\Yacht;
use App\Services\OpenMarineService;
use App\Services\PlatformExportToolsService;
use App\Services\YachtShiftSyncService;
use Illuminate\Http\JsonResponse;

/**
 * Shows exactly what each *real* integration would send for one yacht —
 * Database, OpenMarine XML, YachtShift JSON, and the generic API-platform
 * JSON payload. No Botentekoop/Scanboat tabs: neither exists anywhere in
 * this codebase (no Platform row, no payload logic), and YachtWorld/HISWA/
 * Boat24/YachtFocus/Obato all just consume the generic OpenMarine feed —
 * inventing marketplace-specific tabs for integrations that don't exist
 * would show fabricated data, not a preview of something real.
 */
class MarketplacePreviewController extends Controller
{
    public function __construct(
        private readonly OpenMarineService $openMarine,
        private readonly YachtShiftSyncService $yachtShift,
        private readonly PlatformExportToolsService $exportTools,
    ) {
    }

    public function forYacht(Yacht $yacht): JsonResponse
    {
        $yacht->loadMissing(array_merge(
            ['images', 'location'],
            array_keys(Yacht::SUB_TABLE_MAP)
        ));

        $apiPlatform = Platform::where('is_active', true)
            ->where('export_method', 'api')
            ->orderBy('priority')
            ->first();

        $genericApiJson = $apiPlatform
            ? $this->exportTools->previewPayload($apiPlatform, $yacht->id)
            : ['error' => 'No active API-type platform is configured to preview against.'];

        return response()->json([
            'yacht_id' => $yacht->id,
            'yacht_name' => $yacht->boat_name,
            'database' => $yacht->toArray(),
            'openmarine_xml' => $this->openMarine->generate($yacht),
            'yachtshift_json' => $this->yachtShift->mapExportFields($yacht),
            'generic_api_json' => $genericApiJson,
        ]);
    }
}
