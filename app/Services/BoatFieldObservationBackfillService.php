<?php

namespace App\Services;

use App\Models\BoatField;
use App\Models\BoatFieldMapping;
use App\Models\BoatFieldValueObservation;
use App\Models\Yacht;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BoatFieldObservationBackfillService
{
    /**
     * @param  array<int, string>|null  $sources
     * @return array<string, mixed>
     */
    public function backfill(bool $reset = false, ?array $sources = null): array
    {
        $allowedSources = $this->normalizeSources($sources);

        if ($reset) {
            BoatFieldValueObservation::query()
                ->whereIn('source', $allowedSources)
                ->delete();
        }

        $writtenBySource = array_fill_keys($allowedSources, 0);
        $observationsWritten = 0;
        $fieldsProcessed = 0;

        $fields = BoatField::query()
            ->active()
            ->orderBy('id')
            ->get();

        foreach ($fields as $field) {
            $records = $this->collectFieldObservations($field, $allowedSources);
            if ($records === []) {
                continue;
            }

            BoatFieldValueObservation::query()->upsert(
                $records,
                ['field_id', 'source', 'external_value'],
                ['external_key', 'observed_count', 'last_seen_at', 'updated_at'],
            );

            $fieldsProcessed++;
            $observationsWritten += count($records);

            foreach ($records as $record) {
                $writtenBySource[$record['source']]++;
            }
        }

        return [
            'sources' => $allowedSources,
            'fields_processed' => $fieldsProcessed,
            'observations_written' => $observationsWritten,
            'written_by_source' => $writtenBySource,
            'reset' => $reset,
        ];
    }

    /**
     * @param  array<int, string>  $allowedSources
     * @return array<int, array<string, mixed>>
     */
    private function collectFieldObservations(BoatField $field, array $allowedSources): array
    {
        $query = $this->buildFieldValueQuery($field);
        if ($query === null) {
            return [];
        }

        $records = [];
        $now = now();

        foreach ($query->cursor() as $row) {
            $source = $this->mapYachtSourceToObservationSource($row->yacht_source ?? null);
            if ($source === null || ! in_array($source, $allowedSources, true)) {
                continue;
            }

            $externalValue = $this->normalizeObservedValue($row->raw_value ?? null);
            if ($externalValue === null) {
                continue;
            }

            $bucketKey = $source . '|' . mb_strtolower($externalValue);
            if (! isset($records[$bucketKey])) {
                $records[$bucketKey] = [
                    'field_id' => $field->id,
                    'source' => $source,
                    'external_key' => null,
                    'external_value' => $externalValue,
                    'observed_count' => 0,
                    'last_seen_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $records[$bucketKey]['observed_count']++;

            $lastSeenAt = $row->last_seen_at ?? null;
            if ($lastSeenAt !== null && (
                $records[$bucketKey]['last_seen_at'] === null ||
                (string) $lastSeenAt > (string) $records[$bucketKey]['last_seen_at']
            )) {
                $records[$bucketKey]['last_seen_at'] = $lastSeenAt;
            }
        }

        return array_values($records);
    }

    private function buildFieldValueQuery(BoatField $field): ?Builder
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
                ->select([
                    'yachts.source as yacht_source',
                    'yachts.updated_at as last_seen_at',
                    DB::raw("yachts.{$column} as raw_value"),
                ])
                ->whereNotNull("yachts.{$column}");
        }

        if (! method_exists(Yacht::class, $relation)) {
            return null;
        }

        /** @var HasOne $relationInstance */
        $relationInstance = (new Yacht())->{$relation}();
        $table = $relationInstance->getRelated()->getTable();
        $foreignKey = $relationInstance->getForeignKeyName();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return null;
        }

        return DB::table($table)
            ->join('yachts', "{$table}.{$foreignKey}", '=', 'yachts.id')
            ->select([
                'yachts.source as yacht_source',
                'yachts.updated_at as last_seen_at',
                DB::raw("{$table}.{$column} as raw_value"),
            ])
            ->whereNotNull("{$table}.{$column}");
    }

    /**
     * @param  array<int, string>|null  $sources
     * @return array<int, string>
     */
    private function normalizeSources(?array $sources): array
    {
        $normalized = collect($sources ?? BoatFieldMapping::SOURCES)
            ->map(fn ($source) => Str::lower(trim((string) $source)))
            ->filter(fn ($source) => in_array($source, BoatFieldMapping::SOURCES, true))
            ->unique()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : BoatFieldMapping::SOURCES;
    }

    private function mapYachtSourceToObservationSource(?string $source): ?string
    {
        $normalized = Str::lower(trim((string) $source));
        if ($normalized === '') {
            return null;
        }

        if (Str::contains($normalized, 'yachtshift')) {
            return 'yachtshift';
        }

        if (Str::contains($normalized, ['schepenkring', 'scrape', 'scraper'])) {
            return 'scrape';
        }

        if (Str::contains($normalized, ['import', 'feed', 'broker', 'xml'])) {
            return 'future_import';
        }

        return null;
    }

    private function normalizeObservedValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ($normalized === '') {
            return null;
        }

        return Str::substr($normalized, 0, 191);
    }
}
