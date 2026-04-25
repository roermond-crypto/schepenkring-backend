<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Services\BoatTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoatMatchController extends Controller
{
    /** Maximum year gap allowed for matching (safety rule) */
    private const MAX_YEAR_GAP = 6;

    public function __construct(
        private readonly BoatTemplateService $templateService
    ) {}

    /**
     * POST /api/boats/match
     *
     * Search the yacht database for a matching boat
     * by brand, model, and/or year. Returns the best match plus
     * common specs to enrich AI pipeline context.
     */
    public function match(Request $request): JsonResponse
    {
        $request->validate([
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year'  => 'nullable|integer|min:1900|max:2100',
        ]);

        $brand = trim($request->input('brand', ''));
        $model = trim($request->input('model', ''));
        $year  = $request->input('year') ? (int) $request->input('year') : null;

        if (empty($brand) && empty($model)) {
            return response()->json([
                'matched'       => false,
                'match_type'    => 'none',
                'message'       => 'At least brand or model is required.',
                'boat'          => null,
                'similar_boats_count' => 0,
            ]);
        }

        try {
            // ── Stage 1: Try exact match (brand + model + year) ──────────
            if (!empty($brand) && !empty($model) && $year) {
                $exact = $this->findExactMatch($brand, $model, $year);
                if ($exact) {
                    $similarCount = $this->countSimilar($brand, $model);
                    $template = $this->templateService->findOrCreateTemplate($brand, $model, $year);
                    return response()->json([
                        'matched'             => true,
                        'match_type'          => 'exact',
                        'message'             => "Exact match found: {$exact->manufacturer} {$exact->model} ({$exact->year})",
                        'boat'                => $this->formatBoatResponse($exact),
                        'common_fields'       => $this->getCommonFieldsForBoatType($brand, $model),
                        'similar_boats_count' => $similarCount,
                        'template'            => $template ? $this->formatTemplateResponse($template) : null,
                    ]);
                }
            }

            // ── Stage 2: Try close match (brand + model, closest year within ±6yr) ───
            if (!empty($brand) && !empty($model)) {
                $fuzzy = $this->findFuzzyMatch($brand, $model, $year);
                if ($fuzzy) {
                    $similarCount = $this->countSimilar($brand, $model);
                    $template = $this->templateService->findOrCreateTemplate($brand, $model, $year);
                    return response()->json([
                        'matched'             => true,
                        'match_type'          => 'fuzzy',
                        'message'             => "Similar boat found: {$fuzzy->manufacturer} {$fuzzy->model} ({$fuzzy->year})",
                        'boat'                => $this->formatBoatResponse($fuzzy),
                        'common_fields'       => $this->getCommonFieldsForBoatType($brand, $model),
                        'similar_boats_count' => $similarCount,
                        'template'            => $template ? $this->formatTemplateResponse($template) : null,
                    ]);
                }
            }

            // ── Stage 3: Partial match (brand only) ─────────────────────
            if (!empty($brand)) {
                $partial = $this->findPartialMatch($brand, $model);
                if ($partial) {
                    $similarCount = Yacht::where('manufacturer', 'LIKE', "%{$brand}%")->count();
                    return response()->json([
                        'matched'             => true,
                        'match_type'          => 'partial',
                        'message'             => "Brand match found: {$partial->manufacturer}" . ($partial->model ? " {$partial->model}" : ''),
                        'boat'                => $this->formatBoatResponse($partial),
                        'common_fields'       => $this->getCommonFieldsForBrand($brand),
                        'similar_boats_count' => $similarCount,
                    ]);
                }
            }

            return response()->json([
                'matched'             => false,
                'match_type'          => 'none',
                'message'             => 'No matching boat found in the database.',
                'boat'                => null,
                'similar_boats_count' => 0,
            ]);

        } catch (\Exception $e) {
            Log::error('[BoatMatch] Match failed: ' . $e->getMessage());

            return response()->json([
                'matched'             => false,
                'match_type'          => 'error',
                'message'             => 'Matching service temporarily unavailable.',
                'boat'                => null,
                'similar_boats_count' => 0,
            ], 200);
        }
    }

    private function findExactMatch(string $brand, string $model, int $year): ?Yacht
    {
        return Yacht::whereRaw('LOWER(TRIM(manufacturer)) = ?', [strtolower(trim($brand))])
            ->whereRaw('LOWER(TRIM(model)) = ?', [strtolower(trim($model))])
            ->where('year', $year)
            ->with(['dimensions', 'construction', 'engine', 'accommodation'])
            ->first();
    }

    private function findFuzzyMatch(string $brand, string $model, ?int $year): ?Yacht
    {
        $query = Yacht::whereRaw('LOWER(TRIM(manufacturer)) = ?', [strtolower(trim($brand))])
            ->whereRaw('LOWER(TRIM(model)) = ?', [strtolower(trim($model))])
            ->with(['dimensions', 'construction', 'engine', 'accommodation']);

        if ($year) {
            $query->whereBetween('year', [$year - self::MAX_YEAR_GAP, $year + self::MAX_YEAR_GAP]);
            $query->orderByRaw('ABS(CAST(year AS SIGNED) - ?) ASC', [$year]);
        } else {
            $query->orderByDesc('year');
        }

        return $query->first();
    }

    private function findPartialMatch(string $brand, string $model = ''): ?Yacht
    {
        $query = Yacht::where('manufacturer', 'LIKE', "%{$brand}%")
            ->with(['dimensions', 'construction', 'engine', 'accommodation']);

        if (!empty($model)) {
            $modelWords = explode(' ', $model);
            $query->where(function ($q) use ($modelWords, $model) {
                $q->where('model', 'LIKE', "%{$model}%");
                foreach ($modelWords as $word) {
                    if (strlen($word) >= 2) {
                        $q->orWhere('model', 'LIKE', "%{$word}%");
                    }
                }
            });
        }

        return $query->orderByDesc('year')->first();
    }

    private function countSimilar(string $brand, string $model): int
    {
        return Yacht::whereRaw('LOWER(TRIM(manufacturer)) = ?', [strtolower(trim($brand))])
            ->whereRaw('LOWER(TRIM(model)) = ?', [strtolower(trim($model))])
            ->count();
    }

    private function formatBoatResponse(Yacht $yacht): array
    {
        return [
            'id'                   => $yacht->id,
            'brand'                => $yacht->manufacturer,
            'model'                => $yacht->model,
            'year'                 => $yacht->year,
            'boat_name'            => $yacht->boat_name,
            'boat_type'            => $yacht->boat_type,
            'boat_category'        => $yacht->boat_category,
            'engine_type'          => $yacht->engine?->engine_type ?? null,
            'fuel'                 => $yacht->engine?->fuel ?? null,
            'common_specs'         => array_filter([
                'loa'              => $yacht->dimensions?->loa ?? null,
                'beam'             => $yacht->dimensions?->beam ?? null,
                'draft'            => $yacht->dimensions?->draft ?? null,
                'displacement'     => $yacht->dimensions?->displacement ?? null,
                'horse_power'      => $yacht->engine?->horse_power ?? null,
                'engine_quantity'  => $yacht->engine?->engine_quantity ?? null,
                'cabins'           => $yacht->accommodation?->cabins ?? null,
                'berths'           => $yacht->accommodation?->berths ?? null,
                'max_speed'        => $yacht->engine?->max_speed ?? null,
                'cruising_speed'   => $yacht->engine?->cruising_speed ?? null,
                'ce_category'      => $yacht->ce_category ?? null,
            ], fn($v) => $v !== null && $v !== '' && $v !== 0),
        ];
    }

    private function getCommonFieldsForBoatType(string $brand, string $model): array
    {
        $boats = Yacht::whereRaw('LOWER(TRIM(manufacturer)) = ?', [strtolower(trim($brand))])
            ->whereRaw('LOWER(TRIM(model)) = ?', [strtolower(trim($model))])
            ->with(['dimensions', 'construction', 'engine', 'accommodation'])
            ->limit(20)
            ->get();

        return $this->analyzeFieldPresence($boats);
    }

    private function getCommonFieldsForBrand(string $brand): array
    {
        $boats = Yacht::where('manufacturer', 'LIKE', "%{$brand}%")
            ->with(['dimensions', 'construction', 'engine', 'accommodation'])
            ->limit(30)
            ->get();

        return $this->analyzeFieldPresence($boats);
    }

    private function analyzeFieldPresence($boats): array
    {
        if ($boats->isEmpty()) {
            return [];
        }

        $fieldChecks = [
            'loa', 'beam', 'draft', 'displacement', 'hull_type', 'hull_construction',
            'engine_manufacturer', 'engine_model', 'engine_type', 'horse_power', 'fuel',
            'engine_quantity', 'max_speed', 'cruising_speed', 'drive_type',
            'cabins', 'berths', 'toilet', 'shower', 'heating', 'air_conditioning',
            'ce_category', 'passenger_capacity',
        ];

        $presence = [];
        $total = $boats->count();

        foreach ($fieldChecks as $field) {
            $filled = $boats->filter(function ($yacht) use ($field) {
                $value = $yacht->$field
                    ?? $yacht->dimensions?->$field
                    ?? $yacht->construction?->$field
                    ?? $yacht->engine?->$field
                    ?? $yacht->accommodation?->$field
                    ?? null;

                return $value !== null && $value !== '' && $value !== 'unknown';
            })->count();

            $rate = round($filled / $total, 2);
            if ($rate >= 0.3) {
                $presence[$field] = $rate;
            }
        }

        arsort($presence);
        return $presence;
    }

    private function formatTemplateResponse(\App\Models\BoatTemplate $template): array
    {
        return [
            'template_id'         => $template->id,
            'version'             => $template->version,
            'match_level'         => $template->match_level,
            'source_boat_count'   => $template->source_boat_count,
            'year_range'          => [
                'min' => $template->year_min,
                'max' => $template->year_max,
            ],
            'known_values'        => $template->getPrefilledValues(),
            'required_fields'     => $template->getRequiredFields(),
            'optional_fields'     => $template->getOptionalFields(),
            'missing_fields'      => $template->getMissingFields(),
        ];
    }
}
