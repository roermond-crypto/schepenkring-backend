<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatField;
use App\Models\BoatFieldMapping;
use App\Services\BoatFieldMappingSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BoatFieldMappingController extends Controller
{
    public function index(Request $request, BoatField $boatField): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['nullable', Rule::in(BoatFieldMapping::SOURCES)],
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $source = $validated['source'] ?? null;
        $limit = $validated['limit'] ?? 200;

        $mappings = $boatField->mappings()
            ->when($source, fn ($query) => $query->where('source', $source))
            ->orderBy('source')
            ->orderBy('external_value')
            ->get();

        $observations = $boatField->valueObservations()
            ->when($source, fn ($query) => $query->where('source', $source))
            ->orderByDesc('observed_count')
            ->orderBy('external_value')
            ->limit($limit)
            ->get();

        $mappingCountsBySource = $boatField->mappings()
            ->selectRaw('source, COUNT(*) as mappings_count')
            ->groupBy('source')
            ->pluck('mappings_count', 'source');

        $observationSummaryBySource = $boatField->valueObservations()
            ->selectRaw('source, COUNT(*) as observed_values_count, COALESCE(SUM(observed_count), 0) as observed_total, MAX(last_seen_at) as last_seen_at')
            ->groupBy('source')
            ->get()
            ->keyBy('source');

        return response()->json([
            'data' => [
                'field' => [
                    'id' => $boatField->id,
                    'internal_key' => $boatField->internal_key,
                    'labels_json' => $boatField->labels_json ?? [],
                ],
                'source' => $source,
                'mappings' => $mappings,
                'observations' => $observations,
                'source_summary' => collect(BoatFieldMapping::SOURCES)
                    ->map(fn (string $candidate) => $this->buildSourceSummaryRow(
                        $candidate,
                        $mappingCountsBySource,
                        $observationSummaryBySource,
                    ))
                    ->values(),
            ],
        ]);
    }

    public function update(Request $request, BoatField $boatField): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', Rule::in(BoatFieldMapping::SOURCES)],
            'mappings' => 'nullable|array',
            'mappings.*.external_key' => 'nullable|string|max:120',
            'mappings.*.external_value' => 'required|string|max:191',
            'mappings.*.normalized_value' => 'required|string|max:191',
            'mappings.*.match_type' => ['nullable', Rule::in(BoatFieldMapping::MATCH_TYPES)],
        ]);

        $source = $validated['source'];

        $mappings = DB::transaction(function () use ($boatField, $source, $validated) {
            $boatField->mappings()->where('source', $source)->delete();

            $records = collect($validated['mappings'] ?? [])
                ->map(function (array $mapping) use ($source) {
                    return [
                        'source' => $source,
                        'external_key' => isset($mapping['external_key']) && trim((string) $mapping['external_key']) !== ''
                            ? trim((string) $mapping['external_key'])
                            : null,
                        'external_value' => trim((string) $mapping['external_value']),
                        'normalized_value' => trim((string) $mapping['normalized_value']),
                        'match_type' => strtolower(trim((string) ($mapping['match_type'] ?? 'exact'))),
                    ];
                })
                ->filter(fn (array $mapping) => $mapping['external_value'] !== '' && $mapping['normalized_value'] !== '')
                ->unique(fn (array $mapping) => implode('|', [
                    $mapping['source'],
                    $mapping['external_key'] ?? '',
                    $mapping['external_value'],
                    $mapping['match_type'],
                ]))
                ->values();

            if ($records->isNotEmpty()) {
                $boatField->mappings()->createMany($records->all());
            }

            return $boatField->mappings()
                ->where('source', $source)
                ->orderBy('external_value')
                ->get();
        });

        return response()->json([
            'message' => 'Boat field mappings updated successfully.',
            'data' => [
                'field_id' => $boatField->id,
                'source' => $source,
                'mappings' => $mappings,
            ],
        ]);
    }

    public function generateAiSuggestions(
        BoatField $boatField,
        BoatFieldMappingSuggestionService $mappingSuggestionService,
    ): JsonResponse {
        $result = $mappingSuggestionService->generateAndPersist($boatField);

        $mappingCountsBySource = $boatField->mappings()
            ->selectRaw('source, COUNT(*) as mappings_count')
            ->groupBy('source')
            ->pluck('mappings_count', 'source');

        $observationSummaryBySource = $boatField->valueObservations()
            ->selectRaw('source, COUNT(*) as observed_values_count, COALESCE(SUM(observed_count), 0) as observed_total, MAX(last_seen_at) as last_seen_at')
            ->groupBy('source')
            ->get()
            ->keyBy('source');

        return response()->json([
            'message' => 'AI mapping suggestions generated successfully.',
            'data' => [
                ...$result,
                'source_summary' => collect(BoatFieldMapping::SOURCES)
                    ->map(fn (string $source) => $this->buildSourceSummaryRow(
                        $source,
                        $mappingCountsBySource,
                        $observationSummaryBySource,
                    ))
                    ->values(),
            ],
        ]);
    }

    private function buildSourceSummaryRow(
        string $source,
        Collection $mappingCountsBySource,
        Collection $observationSummaryBySource,
    ): array {
        $observationSummary = $observationSummaryBySource->get($source);

        return [
            'source' => $source,
            'mappings_count' => (int) ($mappingCountsBySource->get($source) ?? 0),
            'observed_values_count' => (int) data_get($observationSummary, 'observed_values_count', 0),
            'observed_total' => (int) data_get($observationSummary, 'observed_total', 0),
            'last_seen_at' => data_get($observationSummary, 'last_seen_at'),
        ];
    }
}
