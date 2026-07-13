<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Models\Yacht;
use App\Services\OpenMarineService;
use App\Services\PlatformExportToolsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fleet-wide Supported/Missing/Errors counts per platform — nothing in
 * this codebase evaluated a specific yacht against a specific platform's
 * requirements before this. Combines three real checks:
 *  - OpenMarineService::validate($yacht) — generic yacht-completeness,
 *    identical for every platform (same for all, computed once per yacht).
 *  - PlatformExportToolsService::validateConfiguration($platform) —
 *    platform-completeness, identical for every yacht.
 *  - A genuinely per-yacht-per-platform check this build adds: whether the
 *    yacht's boat_type has an entry in *this* platform's
 *    openmarine_category_map, when that platform defines one.
 */
class PlatformCompatibilityController extends Controller
{
    public function __construct(
        private readonly OpenMarineService $openMarine,
        private readonly PlatformExportToolsService $exportTools,
    ) {
    }

    public function index(): JsonResponse
    {
        $platforms = Platform::where('is_active', true)->orderBy('priority')->get();
        $yachts = Yacht::whereIn('status', OpenMarineService::EXPORTABLE_STATUSES)
            ->where('is_test', false)
            ->get();

        $rows = [];
        foreach ($platforms as $platform) {
            $counts = $this->countForPlatform($platform, $yachts);
            $rows[] = [
                'platform_id' => $platform->id,
                'platform_name' => $platform->name,
                'supported' => $counts['supported'],
                'missing' => $counts['missing'],
                'errors' => $counts['errors'],
            ];
        }

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /admin/openmarine/compatibility/{platform}?bucket=missing|supported|errors
     */
    public function drillDown(Request $request, Platform $platform): JsonResponse
    {
        $bucket = $request->input('bucket', 'missing');
        $yachts = Yacht::whereIn('status', OpenMarineService::EXPORTABLE_STATUSES)
            ->where('is_test', false)
            ->get();

        $matches = [];
        foreach ($yachts as $yacht) {
            [$classification, $reasons] = $this->classify($platform, $yacht);
            if ($classification === $bucket) {
                $matches[] = [
                    'yacht_id' => $yacht->id,
                    'boat_name' => $yacht->boat_name,
                    'reasons' => $reasons,
                ];
            }
        }

        return response()->json(['data' => $matches, 'bucket' => $bucket]);
    }

    private function countForPlatform(Platform $platform, $yachts): array
    {
        $supported = 0;
        $missing = 0;
        $errors = 0;

        foreach ($yachts as $yacht) {
            [$classification] = $this->classify($platform, $yacht);
            match ($classification) {
                'supported' => $supported++,
                'missing' => $missing++,
                default => $errors++,
            };
        }

        return ['supported' => $supported, 'missing' => $missing, 'errors' => $errors];
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function classify(Platform $platform, Yacht $yacht): array
    {
        $configResult = $this->exportTools->validateConfiguration($platform);
        $configErrors = collect($configResult['issues'])
            ->where('severity', 'error')
            ->pluck('message')
            ->all();

        if (! empty($configErrors)) {
            return ['errors', $configErrors];
        }

        [$yachtErrors] = $this->openMarine->validate($yacht);
        if (! empty($yachtErrors)) {
            return ['missing', $yachtErrors];
        }

        $categoryMap = $platform->openmarine_category_map ?? [];
        if (! empty($categoryMap) && ! array_key_exists($yacht->boat_type, $categoryMap)) {
            return ['missing', ["No category mapping for boat type '{$yacht->boat_type}' on this platform."]];
        }

        return ['supported', []];
    }
}
