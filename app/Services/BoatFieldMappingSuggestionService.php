<?php

namespace App\Services;

use App\Models\BoatField;
use App\Models\BoatFieldMapping;
use App\Models\Yacht;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class BoatFieldMappingSuggestionService
{
    /**
     * @return array<string, mixed>
     */
    public function generateAndPersist(BoatField $field): array
    {
        $databaseValueCandidates = $this->loadDatabaseValueCandidates($field);
        $existingMappings = $field->mappings()
            ->orderBy('source')
            ->orderBy('external_value')
            ->get();

        $observationsBySource = collect(BoatFieldMapping::SOURCES)
            ->mapWithKeys(function (string $source) use ($field) {
                return [
                    $source => $field->valueObservations()
                        ->where('source', $source)
                        ->orderByDesc('observed_count')
                        ->orderBy('external_value')
                        ->get()
                        ->map(fn ($observation) => [
                            'external_key' => $this->normalizeOptionalString($observation->external_key),
                            'external_value' => $this->normalizeRequiredString($observation->external_value),
                            'observed_count' => (int) $observation->observed_count,
                            'last_seen_at' => optional($observation->last_seen_at)?->toIso8601String(),
                        ])
                        ->filter(fn (array $observation) => $observation['external_value'] !== null)
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $observationLookup = $this->buildObservationLookup($observationsBySource);
        $unmappedObservationsBySource = $this->filterUnmappedObservations(
            $observationsBySource,
            $existingMappings,
        );
        $consideredObservations = array_sum(array_map('count', $unmappedObservationsBySource));

        if ($consideredObservations === 0) {
            return [
                'field_id' => $field->id,
                'created_mappings' => 0,
                'skipped_mappings' => 0,
                'considered_observations' => 0,
                'db_existing_values_count' => count($databaseValueCandidates),
                'normalized_candidates_count' => count(
                    $this->buildNormalizedCandidates($field, $existingMappings, $databaseValueCandidates),
                ),
                'created_by_source' => array_fill_keys(BoatFieldMapping::SOURCES, 0),
            ];
        }

        $normalizedCandidates = $this->buildNormalizedCandidates(
            $field,
            $existingMappings,
            $databaseValueCandidates,
        );

        $suggestions = $this->requestSuggestions(
            $field,
            $observationsBySource,
            $unmappedObservationsBySource,
            $existingMappings,
            $normalizedCandidates,
            $databaseValueCandidates,
        );

        $createdBySource = array_fill_keys(BoatFieldMapping::SOURCES, 0);
        $skippedMappings = 0;
        $records = [];
        $seenSuggestionKeys = [];
        $existingMappingKeys = $this->buildExistingMappingLookup($existingMappings);
        $now = now();

        foreach (BoatFieldMapping::SOURCES as $source) {
            $sourceSuggestions = $suggestions[$source] ?? null;
            if (! is_array($sourceSuggestions)) {
                continue;
            }

            foreach ($sourceSuggestions as $suggestion) {
                $normalizedSuggestion = $this->normalizeSuggestion($source, $suggestion);

                if ($normalizedSuggestion === null) {
                    $skippedMappings++;
                    continue;
                }

                $lookupKey = $this->makeLookupKey(
                    $source,
                    $normalizedSuggestion['external_value'],
                );

                if (
                    ! isset($observationLookup[$lookupKey]) ||
                    isset($existingMappingKeys[$lookupKey]) ||
                    isset($seenSuggestionKeys[$lookupKey])
                ) {
                    $skippedMappings++;
                    continue;
                }

                $observation = $observationLookup[$lookupKey];

                $records[] = [
                    'field_id' => $field->id,
                    'source' => $source,
                    'external_key' => $observation['external_key'],
                    'external_value' => $observation['external_value'],
                    'normalized_value' => $normalizedSuggestion['normalized_value'],
                    'match_type' => 'exact',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $seenSuggestionKeys[$lookupKey] = true;
                $createdBySource[$source]++;
            }
        }

        if ($records !== []) {
            DB::transaction(function () use ($records) {
                BoatFieldMapping::query()->insert($records);
            });
        }

        return [
            'field_id' => $field->id,
            'created_mappings' => count($records),
            'skipped_mappings' => $skippedMappings,
            'considered_observations' => $consideredObservations,
            'db_existing_values_count' => count($databaseValueCandidates),
            'normalized_candidates_count' => count($normalizedCandidates),
            'created_by_source' => $createdBySource,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $observationsBySource
     * @param  array<int, array<string, mixed>>  $databaseValueCandidates
     * @return array<string, mixed>
     */
    private function requestSuggestions(
        BoatField $field,
        array $allObservationsBySource,
        array $observationsToMapBySource,
        EloquentCollection $existingMappings,
        array $normalizedCandidates,
        array $databaseValueCandidates,
    ): array {
        $response = Http::withToken($this->resolveApiKey())
            ->timeout((int) config('services.openai.mapping_timeout', 120))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('services.openai.mapping_model', 'gpt-4o-mini'),
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode("\n", [
                            'You normalize yacht listing field values from multiple sources into one internal value.',
                            'Return strict JSON only.',
                            'Use the top-level keys yachtshift, scrape, and future_import.',
                            'Each source value must be an array of objects with the keys external_value and normalized_value.',
                            'Only propose mappings for the provided observations that do not already have a mapping.',
                            'Prefer one of normalized_candidates when possible.',
                            'Use db_existing_values as extra context for how the application already stores the value.',
                            'Do not invent extra sources, extra observations, or explanations.',
                            'Omit observations that are too ambiguous to map confidently.',
                            'When a field is tri_state and no better candidate exists, normalize to yes, no, or unknown.',
                            'When there are no strong normalized candidates, use a short cleaned internal value.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'field' => [
                                'id' => $field->id,
                                'internal_key' => $field->internal_key,
                                'labels' => $field->labels_json ?? [],
                                'field_type' => $field->field_type,
                                'block_key' => $field->block_key,
                                'step_key' => $field->step_key,
                                'storage_relation' => $field->storage_relation,
                                'storage_column' => $field->storage_column,
                                'options' => $field->options_json ?? [],
                            ],
                            'normalized_candidates' => $normalizedCandidates,
                            'db_existing_values' => $databaseValueCandidates,
                            'all_observations' => $allObservationsBySource,
                            'existing_mappings' => $existingMappings
                                ->groupBy('source')
                                ->map(fn (EloquentCollection $mappings) => $mappings
                                    ->map(fn (BoatFieldMapping $mapping) => [
                                        'external_key' => $this->normalizeOptionalString($mapping->external_key),
                                        'external_value' => $mapping->external_value,
                                        'normalized_value' => $mapping->normalized_value,
                                        'match_type' => $mapping->match_type,
                                    ])
                                    ->values()
                                    ->all())
                                ->all(),
                            'observations_to_map' => $observationsToMapBySource,
                            'output_example' => [
                                'yachtshift' => [
                                    [
                                        'external_value' => 'benzine',
                                        'normalized_value' => 'petrol',
                                    ],
                                ],
                                'scrape' => [],
                                'future_import' => [],
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI mapping generation failed with status ' . $response->status() . '.');
        }

        $rawContent = (string) $response->json('choices.0.message.content', '');
        $decoded = json_decode($this->stripMarkdownJson($rawContent), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned invalid mapping suggestion JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $observationsBySource
     * @return array<string, array<string, string|null>>
     */
    private function buildObservationLookup(array $observationsBySource): array
    {
        $lookup = [];

        foreach ($observationsBySource as $source => $observations) {
            foreach ($observations as $observation) {
                $externalValue = $this->normalizeRequiredString($observation['external_value'] ?? null);
                if ($externalValue === null) {
                    continue;
                }

                $lookup[$this->makeLookupKey($source, $externalValue)] = [
                    'external_key' => $this->normalizeOptionalString($observation['external_key'] ?? null),
                    'external_value' => $externalValue,
                ];
            }
        }

        return $lookup;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $observationsBySource
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function filterUnmappedObservations(
        array $observationsBySource,
        EloquentCollection $existingMappings,
    ): array {
        $mappedLookup = $this->buildExistingMappingLookup($existingMappings);

        return collect($observationsBySource)
            ->map(function (array $observations, string $source) use ($mappedLookup) {
                return collect($observations)
                    ->filter(function (array $observation) use ($mappedLookup, $source) {
                        $externalValue = $this->normalizeRequiredString($observation['external_value'] ?? null);

                        return $externalValue !== null
                            && ! isset($mappedLookup[$this->makeLookupKey($source, $externalValue)]);
                    })
                    ->values()
                    ->all();
            })
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function buildExistingMappingLookup(EloquentCollection $existingMappings): array
    {
        return $existingMappings
            ->mapWithKeys(function (BoatFieldMapping $mapping) {
                $externalValue = $this->normalizeRequiredString($mapping->external_value);
                if ($externalValue === null) {
                    return [];
                }

                return [
                    $this->makeLookupKey($mapping->source, $externalValue) => true,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{value: string, observed_count: int}>
     */
    private function loadDatabaseValueCandidates(BoatField $field): array
    {
        $query = $this->buildDatabaseValueQuery($field);
        if ($query === null) {
            return [];
        }

        $counts = [];

        foreach ($query->cursor() as $row) {
            $value = $this->normalizeRequiredString($row->raw_value ?? null);
            if ($value === null) {
                continue;
            }

            $bucketKey = mb_strtolower($value);

            if (! isset($counts[$bucketKey])) {
                $counts[$bucketKey] = [
                    'value' => $value,
                    'observed_count' => 0,
                ];
            }

            $counts[$bucketKey]['observed_count']++;
        }

        return collect($counts)
            ->sortByDesc('observed_count')
            ->values()
            ->take(100)
            ->all();
    }

    private function buildDatabaseValueQuery(BoatField $field): ?Builder
    {
        $column = trim((string) $field->storage_column);
        if ($column === '') {
            return null;
        }

        $relation = $field->storage_relation !== null && trim((string) $field->storage_relation) !== ''
            ? trim((string) $field->storage_relation)
            : null;

        if ($relation === null) {
            if (! Schema::hasColumn('yachts', $column)) {
                return null;
            }

            return DB::table('yachts')
                ->selectRaw("yachts.{$column} as raw_value")
                ->whereNotNull("yachts.{$column}");
        }

        if (! method_exists(Yacht::class, $relation)) {
            return null;
        }

        /** @var HasOne $relationInstance */
        $relationInstance = (new Yacht())->{$relation}();
        $table = $relationInstance->getRelated()->getTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return null;
        }

        return DB::table($table)
            ->selectRaw("{$table}.{$column} as raw_value")
            ->whereNotNull("{$table}.{$column}");
    }

    /**
     * @param  array<int, array{value: string, observed_count: int}>  $databaseValueCandidates
     * @return array<int, array<string, mixed>>
     */
    private function buildNormalizedCandidates(
        BoatField $field,
        EloquentCollection $existingMappings,
        array $databaseValueCandidates,
    ): array {
        $candidates = [];

        foreach ($field->options_json ?? [] as $option) {
            if (! is_array($option)) {
                continue;
            }

            $value = $this->normalizeRequiredString($option['value'] ?? null);
            if ($value === null) {
                continue;
            }

            $labels = is_array($option['labels'] ?? null) ? $option['labels'] : [];
            $candidates[mb_strtolower($value)] = [
                'value' => $value,
                'source' => 'field_option',
                'labels' => $labels,
                'label' => $this->normalizeOptionalString($option['label'] ?? null),
            ];
        }

        foreach ($existingMappings as $mapping) {
            $value = $this->normalizeRequiredString($mapping->normalized_value);
            if ($value === null) {
                continue;
            }

            $key = mb_strtolower($value);

            if (! isset($candidates[$key])) {
                $candidates[$key] = [
                    'value' => $value,
                    'source' => 'existing_mapping',
                ];
            }
        }

        foreach ($databaseValueCandidates as $candidate) {
            $value = $this->normalizeRequiredString($candidate['value'] ?? null);
            if ($value === null) {
                continue;
            }

            $key = mb_strtolower($value);

            if (! isset($candidates[$key])) {
                $candidates[$key] = [
                    'value' => $value,
                    'source' => 'database',
                    'observed_count' => (int) ($candidate['observed_count'] ?? 0),
                ];
            }
        }

        if ($field->field_type === 'tri_state') {
            foreach (['yes', 'no', 'unknown'] as $value) {
                $key = mb_strtolower($value);

                if (! isset($candidates[$key])) {
                    $candidates[$key] = [
                        'value' => $value,
                        'source' => 'tri_state_default',
                    ];
                }
            }
        }

        return array_values($candidates);
    }

    /**
     * @param  array<string, mixed>  $suggestion
     * @return array{external_value: string, normalized_value: string}|null
     */
    private function normalizeSuggestion(string $source, array $suggestion): ?array
    {
        if (! in_array($source, BoatFieldMapping::SOURCES, true)) {
            return null;
        }

        $externalValue = $this->normalizeRequiredString($suggestion['external_value'] ?? null);
        $normalizedValue = $this->normalizeRequiredString($suggestion['normalized_value'] ?? null);

        if ($externalValue === null || $normalizedValue === null) {
            return null;
        }

        return [
            'external_value' => $externalValue,
            'normalized_value' => $normalizedValue,
        ];
    }

    private function resolveApiKey(): string
    {
        $apiKey = config('services.openai.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('OpenAI API key is not configured for mapping generation.');
        }

        return $apiKey;
    }

    private function makeLookupKey(string $source, string $externalValue): string
    {
        return Str::lower(trim($source)) . '|' . mb_strtolower(trim($externalValue));
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = $this->normalizeRequiredString($value);

        return $normalized === null ? null : $normalized;
    }

    private function normalizeRequiredString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function stripMarkdownJson(string $content): string
    {
        $trimmed = trim($content);

        if (Str::startsWith($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }
}
