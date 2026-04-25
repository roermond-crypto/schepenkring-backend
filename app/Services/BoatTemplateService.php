<?php

namespace App\Services;

use App\Models\BoatTemplate;
use App\Models\Yacht;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Boat Template Service
 *
 * Generates, retrieves, and updates templates from matching boats in the database.
 * Templates store the structure + known values for a brand/model combination,
 * so Step 2 forms are pre-populated with deterministic data instead of empty fields.
 */
class BoatTemplateService
{
    /** Fill rate threshold for "required" fields (>80%) */
    private const REQUIRED_THRESHOLD = 0.80;

    /** Fill rate threshold for "optional" fields (>30%) */
    private const OPTIONAL_THRESHOLD = 0.30;

    /** Maximum year gap allowed for matching */
    private const MAX_YEAR_GAP = 6;

    /** Minimum consistency rate for a value to be considered "known" */
    private const VALUE_CONSISTENCY_THRESHOLD = 0.70;

    /** Fields to inspect across source boats */
    private const TEMPLATE_FIELDS = [
        // Core identity
        'boat_type', 'boat_category', 'new_or_used',
        // Dimensions
        'loa', 'lwl', 'beam', 'draft', 'air_draft', 'displacement', 'ballast',
        'passenger_capacity',
        // Construction
        'designer', 'builder', 'where', 'hull_colour', 'hull_construction',
        'hull_type', 'hull_number', 'super_structure_colour',
        'super_structure_construction', 'deck_colour', 'deck_construction',
        'cockpit_type', 'control_type', 'flybridge',
        // Engine
        'engine_manufacturer', 'engine_model', 'engine_type',
        'horse_power', 'fuel', 'engine_quantity',
        'cruising_speed', 'max_speed', 'drive_type', 'propulsion',
        // Accommodation
        'cabins', 'berths', 'toilet', 'shower', 'bath',
        'heating', 'air_conditioning',
        // Navigation
        'compass', 'gps', 'radar', 'autopilot', 'vhf', 'plotter',
        'depth_instrument', 'wind_instrument', 'speed_instrument',
        'navigation_lights',
        // Safety
        'life_raft', 'epirb', 'fire_extinguisher', 'bilge_pump',
        'mob_system', 'life_jackets', 'radar_reflector', 'flares',
        // Electrical
        'battery', 'battery_charger', 'generator', 'inverter',
        'shorepower', 'solar_panel', 'wind_generator', 'voltage',
        // Deck Equipment
        'anchor', 'anchor_winch', 'bimini', 'spray_hood',
        'swimming_platform', 'swimming_ladder', 'teak_deck',
        'cockpit_table', 'dinghy', 'covers', 'fenders',
        // Comfort
        'oven', 'microwave', 'fridge', 'freezer', 'cooker',
        'television', 'cd_player', 'dvd_player', 'satellite_reception',
        // CE
        'ce_category',
    ];

    public function findOrCreateTemplate(string $brand, string $model, ?int $year = null): ?BoatTemplate
    {
        $brand = trim($brand);
        $model = trim($model);

        if (empty($brand) || empty($model)) {
            return null;
        }

        $existing = BoatTemplate::active()
            ->forBrandModel($brand, $model)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->generateTemplate($brand, $model, $year);
    }

    public function generateTemplate(string $brand, string $model, ?int $year = null): ?BoatTemplate
    {
        $matchingBoats = $this->findMatchingBoats($brand, $model, $year);

        if ($matchingBoats->isEmpty()) {
            return null;
        }

        $matchLevel = $this->determineMatchLevel($matchingBoats, $year);
        $templateData = $this->buildTemplateData($matchingBoats);

        $years = $matchingBoats->pluck('year')->filter()->map(fn ($y) => (int) $y);
        $yearMin = $years->isNotEmpty() ? $years->min() : null;
        $yearMax = $years->isNotEmpty() ? $years->max() : null;

        return BoatTemplate::updateOrCreate(
            [
                'brand' => $brand,
                'model' => $model,
                'match_level' => $matchLevel,
            ],
            [
                'year_min' => $yearMin,
                'year_max' => $yearMax,
                'version' => 1,
                'source_boat_ids' => $matchingBoats->pluck('id')->values()->all(),
                'source_boat_count' => $matchingBoats->count(),
                'fields_json' => $templateData['fields'],
                'known_values_json' => $templateData['known_values'],
                'required_fields_json' => $templateData['required_fields'],
                'optional_fields_json' => $templateData['optional_fields'],
                'missing_fields_json' => $templateData['missing_fields'],
                'field_stats_json' => $templateData['field_stats'],
                'is_active' => true,
            ]
        );
    }

    public function updateTemplateFromBoatSave(Yacht $yacht): ?BoatTemplate
    {
        $brand = trim((string) $yacht->manufacturer);
        $model = trim((string) $yacht->model);

        if (empty($brand) || empty($model)) {
            return null;
        }

        $template = BoatTemplate::active()
            ->forBrandModel($brand, $model)
            ->first();

        if (!$template) {
            return $this->generateTemplate($brand, $model, $yacht->year);
        }

        $matchingBoats = $this->findMatchingBoats($brand, $model, null);

        if ($matchingBoats->isEmpty()) {
            return $template;
        }

        $templateData = $this->buildTemplateData($matchingBoats);
        $years = $matchingBoats->pluck('year')->filter()->map(fn ($y) => (int) $y);

        $template->update([
            'year_min' => $years->isNotEmpty() ? $years->min() : $template->year_min,
            'year_max' => $years->isNotEmpty() ? $years->max() : $template->year_max,
            'version' => $template->version + 1,
            'source_boat_ids' => $matchingBoats->pluck('id')->values()->all(),
            'source_boat_count' => $matchingBoats->count(),
            'fields_json' => $templateData['fields'],
            'known_values_json' => $templateData['known_values'],
            'required_fields_json' => $templateData['required_fields'],
            'optional_fields_json' => $templateData['optional_fields'],
            'missing_fields_json' => $templateData['missing_fields'],
            'field_stats_json' => $templateData['field_stats'],
        ]);

        return $template;
    }

    private function findMatchingBoats(string $brand, string $model, ?int $year): Collection
    {
        $query = Yacht::query()
            ->whereRaw('LOWER(TRIM(manufacturer)) = ?', [strtolower(trim($brand))])
            ->whereRaw('LOWER(TRIM(model)) = ?', [strtolower(trim($model))])
            ->with(['dimensions', 'construction', 'engine', 'accommodation',
                     'electrical', 'navigation', 'safety', 'comfort',
                     'deckEquipment', 'rigging']);

        if ($year !== null) {
            $query->whereBetween('year', [$year - self::MAX_YEAR_GAP, $year + self::MAX_YEAR_GAP]);
            $query->orderByRaw('ABS(CAST(year AS SIGNED) - ?) ASC', [$year]);
        } else {
            $query->orderByDesc('year');
        }

        return $query->limit(50)->get();
    }

    private function determineMatchLevel(Collection $boats, ?int $year): string
    {
        if ($year === null) {
            return 'model_only';
        }

        $hasExactYear = $boats->contains(fn ($boat) => (int) $boat->year === $year);
        if ($hasExactYear) {
            return 'exact';
        }

        $hasCloseYear = $boats->contains(fn ($boat) =>
            abs((int) $boat->year - $year) <= 2
        );

        return $hasCloseYear ? 'close' : 'model_only';
    }

    private function buildTemplateData(Collection $boats): array
    {
        $totalBoats = $boats->count();
        if ($totalBoats === 0) {
            return [
                'fields' => [],
                'known_values' => [],
                'required_fields' => [],
                'optional_fields' => [],
                'missing_fields' => [],
                'field_stats' => [],
            ];
        }

        $fieldStats = [];
        $knownValues = [];
        $requiredFields = [];
        $optionalFields = [];
        $missingFields = [];

        foreach (self::TEMPLATE_FIELDS as $field) {
            $values = [];
            $filledCount = 0;

            foreach ($boats as $boat) {
                $value = $this->getFieldValue($boat, $field);

                if ($value !== null && $value !== '' && $value !== 'unknown') {
                    $filledCount++;
                    $normalizedValue = strtolower(trim((string) $value));
                    $values[$normalizedValue] = ($values[$normalizedValue] ?? 0) + 1;
                }
            }

            $fillRate = $filledCount / $totalBoats;

            arsort($values);
            $topValue = !empty($values) ? array_key_first($values) : null;
            $topValueCount = $topValue !== null ? $values[$topValue] : 0;
            $valueConsistency = $filledCount > 0 ? ($topValueCount / $filledCount) : 0;

            $fieldStats[$field] = [
                'fill_rate' => round($fillRate, 2),
                'filled_count' => $filledCount,
                'total_count' => $totalBoats,
                'top_value' => $topValue,
                'value_consistency' => round($valueConsistency, 2),
                'value_distribution' => array_slice($values, 0, 5, true),
            ];

            if ($fillRate >= self::REQUIRED_THRESHOLD) {
                $requiredFields[] = $field;
            } elseif ($fillRate >= self::OPTIONAL_THRESHOLD) {
                $optionalFields[] = $field;
            } else {
                $missingFields[] = $field;
            }

            if ($topValue !== null && $valueConsistency >= self::VALUE_CONSISTENCY_THRESHOLD && $fillRate >= self::OPTIONAL_THRESHOLD) {
                $originalValue = null;
                foreach ($boats as $boat) {
                    $v = $this->getFieldValue($boat, $field);
                    if ($v !== null && strtolower(trim((string) $v)) === $topValue) {
                        $originalValue = $v;
                        break;
                    }
                }

                $knownValues[$field] = $originalValue ?? $topValue;
            }
        }

        return [
            'fields' => self::TEMPLATE_FIELDS,
            'known_values' => $knownValues,
            'required_fields' => $requiredFields,
            'optional_fields' => $optionalFields,
            'missing_fields' => $missingFields,
            'field_stats' => $fieldStats,
        ];
    }

    private function getFieldValue(Yacht $yacht, string $field): mixed
    {
        foreach (Yacht::SUB_TABLE_MAP as $relation => $fields) {
            if (in_array($field, $fields, true)) {
                return $yacht->{$relation}?->{$field} ?? null;
            }
        }

        return $yacht->{$field} ?? null;
    }
}
