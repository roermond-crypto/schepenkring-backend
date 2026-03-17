<?php

namespace App\Services;

use App\Models\BoatField;
use App\Models\BoatFieldPriority;
use App\Models\Yacht;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BoatFieldConfigService
{
    public function build(?string $boatTypeKey = null, ?string $stepKey = null, ?string $locale = null): array
    {
        $normalizedBoatType = $this->normalizeBoatTypeKey($boatTypeKey);
        $normalizedLocale = $this->normalizeLocale($locale);

        $query = BoatField::query()
            ->active()
            ->with(['priorities' => fn ($relation) => $relation->orderBy('boat_type_key')])
            ->orderBy('step_key')
            ->orderBy('block_key')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($stepKey !== null && trim($stepKey) !== '') {
            $query->where('step_key', trim($stepKey));
        }

        $fields = $query->get();
        $blocks = [];

        foreach ($fields as $field) {
            $priority = $this->resolvePriority($field, $normalizedBoatType);
            if ($priority === null) {
                continue;
            }

            $blockKey = $field->block_key;
            if (! isset($blocks[$blockKey])) {
                $blocks[$blockKey] = [
                    'block_key' => $blockKey,
                    'label' => $this->humanizeKey($blockKey),
                    'primary_fields' => [],
                    'secondary_fields' => [],
                    'secondary_count' => 0,
                ];
            }

            $payload = [
                'id' => $field->id,
                'internal_key' => $field->internal_key,
                'label' => $field->labelForLocale($normalizedLocale),
                'labels' => $field->labels_json ?? [],
                'options' => $this->resolveOptions($field, $normalizedLocale),
                'field_type' => $field->field_type,
                'block_key' => $field->block_key,
                'step_key' => $field->step_key,
                'sort_order' => $field->sort_order,
                'priority' => $priority,
                'storage_relation' => $field->storage_relation,
                'storage_column' => $field->storage_column,
                'ai_relevance' => $field->ai_relevance,
            ];

            if ($priority === 'secondary') {
                $blocks[$blockKey]['secondary_fields'][] = $payload;
                $blocks[$blockKey]['secondary_count']++;
            } else {
                $blocks[$blockKey]['primary_fields'][] = $payload;
            }
        }

        return [
            'boat_type' => $normalizedBoatType,
            'step' => $stepKey !== null && trim($stepKey) !== '' ? trim($stepKey) : null,
            'locale' => $normalizedLocale,
            'blocks' => array_values($blocks),
        ];
    }

    public function storageTargets(): array
    {
        $coreColumns = array_values(array_unique((new Yacht())->getFillable()));
        sort($coreColumns);

        $targets = [[
            'relation' => null,
            'label' => 'Yacht',
            'columns' => $coreColumns,
        ]];

        foreach (Yacht::SUB_TABLE_MAP as $relation => $columns) {
            $sortedColumns = array_values(array_unique($columns));
            sort($sortedColumns);

            $targets[] = [
                'relation' => $relation,
                'label' => $this->humanizeKey($relation),
                'columns' => $sortedColumns,
            ];
        }

        return $targets;
    }

    public function assertValidStorageTarget(?string $storageRelation, string $storageColumn): void
    {
        $column = trim($storageColumn);
        if ($column === '') {
            throw ValidationException::withMessages([
                'storage_column' => 'The storage column is required.',
            ]);
        }

        $relation = $storageRelation !== null && trim($storageRelation) !== ''
            ? trim($storageRelation)
            : null;

        if ($relation === null) {
            $allowedColumns = (new Yacht())->getFillable();
            if (! in_array($column, $allowedColumns, true)) {
                throw ValidationException::withMessages([
                    'storage_column' => "The storage column [{$column}] is not a valid yacht column.",
                ]);
            }

            return;
        }

        $subTableMap = Yacht::SUB_TABLE_MAP;
        if (! array_key_exists($relation, $subTableMap)) {
            throw ValidationException::withMessages([
                'storage_relation' => "The storage relation [{$relation}] is not supported.",
            ]);
        }

        if (! in_array($column, $subTableMap[$relation], true)) {
            throw ValidationException::withMessages([
                'storage_column' => "The storage column [{$column}] is not valid for relation [{$relation}].",
            ]);
        }
    }

    public function normalizeBoatTypeKey(?string $boatTypeKey): ?string
    {
        if (! is_string($boatTypeKey)) {
            return null;
        }

        $normalized = Str::of($boatTypeKey)
            ->lower()
            ->trim()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        return $normalized !== '' ? $normalized : null;
    }

    private function resolvePriority(BoatField $field, ?string $boatTypeKey): ?string
    {
        $priorities = $field->priorities;
        if ($priorities->isEmpty()) {
            return 'primary';
        }

        if ($boatTypeKey !== null) {
            $specific = $priorities->firstWhere('boat_type_key', $boatTypeKey);
            if ($specific !== null) {
                return $specific->priority;
            }
        }

        $default = $priorities->firstWhere('boat_type_key', 'default');
        if ($default !== null) {
            return $default->priority;
        }

        return null;
    }

    private function normalizeLocale(?string $locale): string
    {
        $normalized = Str::lower(Str::substr((string) ($locale ?: app()->getLocale() ?: 'en'), 0, 2));

        return $normalized !== '' ? $normalized : 'en';
    }

    private function humanizeKey(string $value): string
    {
        return (string) Str::of($value)
            ->replace(['_', '-'], ' ')
            ->title();
    }

    private function resolveOptions(BoatField $field, string $locale): array
    {
        return collect($field->options_json ?? [])
            ->map(function ($option) use ($locale) {
                if (! is_array($option)) {
                    return null;
                }

                $value = trim((string) ($option['value'] ?? ''));
                if ($value === '') {
                    return null;
                }

                $labels = is_array($option['labels'] ?? null) ? $option['labels'] : [];
                $label = '';

                foreach ([$locale, 'en', 'nl', 'de', 'fr'] as $candidate) {
                    $candidateLabel = trim((string) ($labels[$candidate] ?? ''));
                    if ($candidateLabel !== '') {
                        $label = $candidateLabel;
                        break;
                    }
                }

                if ($label === '') {
                    $label = trim((string) ($option['label'] ?? ''));
                }

                if ($label === '') {
                    $label = $this->humanizeKey($value);
                }

                return [
                    'value' => $value,
                    'label' => $label,
                    'labels' => $labels,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
