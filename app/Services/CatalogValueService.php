<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BoatField;
use App\Models\CatalogValue;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Value governance for the reusable catalog fields (Brand, Model, Boat Type,
 * etc.) configured via BoatField.enable_autocomplete. This is the single
 * place that normalizes, fuzzy-matches, creates, archives, and merges
 * catalog_values rows — every entry point (search, wizard inline-create,
 * settings-page value management) goes through here so duplicate
 * prevention and audit logging never drift between call sites.
 */
class CatalogValueService
{
    private const FUZZY_SUGGEST_THRESHOLD = 0.72;

    public function __construct(
        private readonly CopilotFuzzyMatcher $fuzzyMatcher,
        private readonly ActivityFeedService $activityFeed,
    ) {
    }

    public function normalize(string $value): string
    {
        return $this->fuzzyMatcher->normalize($value);
    }

    /**
     * Typeahead search: active values ranked by fuzzy score against $query,
     * falling back to plain LIKE ordering when $query is empty (browse mode).
     */
    public function search(string $fieldKey, string $query, ?int $parentValueId, int $limit = 20): Collection
    {
        $candidates = CatalogValue::query()
            ->forField($fieldKey)
            ->active()
            // Inclusive, not exclusive: a value with no recorded parent (e.g.
            // a model name backfilled before brand linkage existed, or one
            // shared across multiple brands) stays visible for every brand
            // rather than disappearing once any hierarchy is introduced.
            // Only a value explicitly tied to a *different* parent is hidden.
            ->when(
                $parentValueId !== null,
                fn ($q) => $q->where(fn ($sub) => $sub
                    ->whereNull('parent_value_id')
                    ->orWhere('parent_value_id', $parentValueId)),
            )
            ->orderByDesc('usage_count')
            ->limit(500)
            ->get();

        if (trim($query) === '') {
            return $candidates->sortByDesc('usage_count')->take($limit)->values();
        }

        return $candidates
            ->map(fn (CatalogValue $value) => [
                'value' => $value,
                'score' => $this->fuzzyMatcher->score($query, $value->value),
            ])
            ->filter(fn (array $row) => $row['score'] > 0.15)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('value')
            ->values();
    }

    /**
     * Near-duplicates for $query that aren't an exact normalized match —
     * the "did you mean X?" suggestions shown before a new value is created.
     */
    public function suggestSimilar(string $fieldKey, string $query, int $limit = 5): Collection
    {
        $normalizedQuery = $this->normalize($query);
        if ($normalizedQuery === '') {
            return collect();
        }

        return CatalogValue::query()
            ->forField($fieldKey)
            ->active()
            ->where('normalized_value', '!=', $normalizedQuery)
            ->get()
            ->map(fn (CatalogValue $value) => [
                'value' => $value,
                'score' => $this->fuzzyMatcher->score($query, $value->value),
            ])
            ->filter(fn (array $row) => $row['score'] >= self::FUZZY_SUGGEST_THRESHOLD)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('value')
            ->values();
    }

    public function findExact(string $fieldKey, string $value): ?CatalogValue
    {
        return CatalogValue::query()
            ->forField($fieldKey)
            ->where('normalized_value', $this->normalize($value))
            ->first();
    }

    /**
     * Creates a new catalog value, or returns the existing one on an exact
     * normalized match (idempotent — callers don't need to check first).
     * Throws a 422 with suggestions if a fuzzy near-duplicate exists and the
     * caller hasn't confirmed via $force.
     *
     * @throws ValidationException
     */
    public function findOrCreate(
        string $fieldKey,
        string $value,
        ?User $actor = null,
        string $createdVia = 'manual',
        ?int $parentValueId = null,
        bool $force = false,
    ): CatalogValue {
        $value = trim($value);
        if ($value === '') {
            throw ValidationException::withMessages(['value' => 'Value cannot be empty.']);
        }

        $existing = $this->findExact($fieldKey, $value);
        if ($existing) {
            return $existing->status === CatalogValue::STATUS_ARCHIVED && $existing->merged_into_id
                ? $existing->mergedInto
                : $existing;
        }

        $field = BoatField::query()->where('internal_key', $fieldKey)->first();
        if ($field && ! $field->allow_new_values && $createdVia === 'manual') {
            throw ValidationException::withMessages(['value' => 'New values are not allowed for this field.']);
        }

        $fuzzyEnabled = $field?->fuzzy_matching ?? true;
        if ($fuzzyEnabled && ! $force) {
            $similar = $this->suggestSimilar($fieldKey, $value, 5);
            if ($similar->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'value' => 'Similar values already exist. Confirm to create a new value anyway.',
                    '_suggestions' => $similar->all(),
                ]);
            }
        }

        $created = CatalogValue::create([
            'field_key' => $fieldKey,
            'value' => $value,
            'normalized_value' => $this->normalize($value),
            'usage_count' => 0,
            'status' => CatalogValue::STATUS_ACTIVE,
            'parent_value_id' => $parentValueId,
            'created_via' => $createdVia,
            'created_by' => $actor?->id,
        ]);

        $this->activityFeed->record(
            'catalog_value',
            $created->id,
            'catalog_value_created',
            sprintf('"%s" added to %s', $value, $fieldKey),
            ['field_key' => $fieldKey, 'value' => $value, 'created_via' => $createdVia],
            $actor,
        );

        AuditLog::create([
            'action' => 'catalog_value.created',
            'risk_level' => 'low',
            'result' => 'success',
            'entity_type' => 'catalog_value',
            'entity_id' => $created->id,
            'actor_id' => $actor?->id,
            'meta' => ['field_key' => $fieldKey, 'value' => $value, 'created_via' => $createdVia],
        ]);

        return $created;
    }

    /**
     * Archives a value. Blocked while usage_count > 0 unless a merge target
     * is given, in which case archive() delegates to merge().
     *
     * @throws ValidationException
     */
    public function archive(CatalogValue $catalogValue, ?User $actor = null, ?int $mergeIntoId = null): CatalogValue
    {
        if ($mergeIntoId) {
            return $this->merge($catalogValue, CatalogValue::findOrFail($mergeIntoId), $actor);
        }

        if ($catalogValue->usage_count > 0) {
            throw ValidationException::withMessages([
                'value' => "This value is used by {$catalogValue->usage_count} yacht(s). Merge it into another value to archive it.",
            ]);
        }

        $catalogValue->update(['status' => CatalogValue::STATUS_ARCHIVED]);

        $this->activityFeed->record(
            'catalog_value',
            $catalogValue->id,
            'catalog_value_archived',
            sprintf('"%s" archived', $catalogValue->value),
            ['field_key' => $catalogValue->field_key],
            $actor,
        );

        AuditLog::create([
            'action' => 'catalog_value.archived',
            'risk_level' => 'low',
            'result' => 'success',
            'entity_type' => 'catalog_value',
            'entity_id' => $catalogValue->id,
            'actor_id' => $actor?->id,
            'meta' => ['field_key' => $catalogValue->field_key, 'value' => $catalogValue->value],
        ]);

        return $catalogValue->fresh();
    }

    /**
     * Merges $source into $target: reassigns every yacht row referencing
     * $source's value (via the governing BoatField's storage_relation/
     * storage_column) to $target's value, archives $source, and carries its
     * usage_count over.
     */
    public function merge(CatalogValue $source, CatalogValue $target, ?User $actor = null): CatalogValue
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['target' => 'Cannot merge a value into itself.']);
        }

        if ($source->field_key !== $target->field_key) {
            throw ValidationException::withMessages(['target' => 'Both values must belong to the same field.']);
        }

        $field = BoatField::query()->where('internal_key', $source->field_key)->first();
        $reassigned = 0;

        DB::transaction(function () use ($source, $target, $field, $actor, &$reassigned) {
            if ($field) {
                $reassigned = $this->reassignYachtValues($field, $source->value, $target->value);
            }

            $source->update([
                'status' => CatalogValue::STATUS_ARCHIVED,
                'merged_into_id' => $target->id,
            ]);

            $target->increment('usage_count', $reassigned);

            $this->activityFeed->record(
                'catalog_value',
                $target->id,
                'catalog_value_merged',
                sprintf('"%s" merged into "%s" (%d yacht(s) reassigned)', $source->value, $target->value, $reassigned),
                ['field_key' => $source->field_key, 'from' => $source->value, 'into' => $target->value, 'reassigned' => $reassigned],
                $actor,
            );

            AuditLog::create([
                'action' => 'catalog_value.merged',
                'risk_level' => 'medium',
                'result' => 'success',
                'entity_type' => 'catalog_value',
                'entity_id' => $target->id,
                'actor_id' => $actor?->id,
                'meta' => [
                    'field_key' => $source->field_key,
                    'from_id' => $source->id,
                    'from_value' => $source->value,
                    'into_id' => $target->id,
                    'into_value' => $target->value,
                    'reassigned' => $reassigned,
                ],
            ]);
        });

        return $target->fresh();
    }

    /**
     * Recomputes usage_count for every active value of $fieldKey from the
     * live yacht data. Used by the seeder/backfill and can be re-run safely.
     */
    public function recalculateUsageCounts(string $fieldKey): void
    {
        $field = BoatField::query()->where('internal_key', $fieldKey)->first();
        if (! $field) {
            return;
        }

        $counts = $this->columnValueCounts($field);

        CatalogValue::query()->forField($fieldKey)->active()->each(function (CatalogValue $catalogValue) use ($counts) {
            $catalogValue->update(['usage_count' => $counts[$catalogValue->normalized_value] ?? 0]);
        });
    }

    private function columnValueCounts(BoatField $field): array
    {
        $query = $this->yachtColumnQuery($field);
        if (! $query) {
            return [];
        }

        $column = $field->storage_column;

        return $query
            ->selectRaw("{$column} as raw_value, COUNT(*) as total")
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->pluck('total', 'raw_value')
            ->mapWithKeys(fn ($total, $rawValue) => [$this->normalize((string) $rawValue) => (int) $total])
            ->all();
    }

    private function reassignYachtValues(BoatField $field, string $fromValue, string $toValue): int
    {
        $query = $this->yachtColumnQuery($field);
        if (! $query) {
            return 0;
        }

        return $query->where($field->storage_column, $fromValue)
            ->update([$field->storage_column => $toValue]);
    }

    /**
     * Returns a query builder scoped to whichever table actually holds this
     * field's value — the main `yachts` table, or one of the 1:1 detail
     * sub-tables addressed via BoatField.storage_relation.
     */
    private function yachtColumnQuery(BoatField $field): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|null
    {
        if (! $field->storage_column) {
            return null;
        }

        if (! $field->storage_relation) {
            return Yacht::query();
        }

        if (! method_exists(Yacht::class, $field->storage_relation)) {
            return null;
        }

        $relation = (new Yacht())->{$field->storage_relation}();
        $relatedTable = $relation->getRelated()->getTable();

        return DB::table($relatedTable);
    }
}
