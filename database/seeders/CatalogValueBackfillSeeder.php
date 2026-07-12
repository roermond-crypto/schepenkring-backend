<?php

namespace Database\Seeders;

use App\Models\BoatField;
use App\Models\CatalogValue;
use App\Models\Yacht;
use App\Services\CopilotFuzzyMatcher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Turns on database-backed autocomplete for the core identity fields (Brand,
 * Model, Boat Type, Boat Category, Steering) plus the existing spec fields
 * that make sense as governed catalogs (Builder, Designer, Engine
 * Manufacturer, Engine Model, Fuel, Hull Construction), and backfills
 * catalog_values from whatever values already live on existing yachts so
 * nothing entered before this feature existed gets lost.
 *
 * Safe to re-run: BoatField rows are updateOrCreate'd, catalog_values are
 * findOrCreate'd by normalized value per field.
 */
class CatalogValueBackfillSeeder extends Seeder
{
    /**
     * internal_key => [storage_relation, storage_column, label]. The first
     * four don't exist in BoatFieldSeeder at all (WizardStep2 renders them
     * as hardcoded inputs, not via the dynamic BoatField block renderer) —
     * they're created here purely so /yachts/settings can configure them.
     */
    private const IDENTITY_FIELDS = [
        'manufacturer' => [null, 'manufacturer', 'Brand'],
        'model' => [null, 'model', 'Model'],
        'boat_type' => [null, 'boat_type', 'Boat Type'],
        'boat_category' => [null, 'boat_category', 'Boat Category'],
        'steering_system' => [null, 'steering_system', 'Steering'],
        'location_country' => [null, 'location_country', 'Country'],
    ];

    /**
     * location_country is a brand-new column with no existing yacht data to
     * backfill from, so it starts empty unless seeded directly — a common
     * starter list covering this brokerage's actual market (NL-based,
     * Western Europe-heavy) plus the rest of the EU and a few common
     * non-EU flag states, rather than shipping an empty autocomplete.
     */
    private const STARTER_COUNTRIES = [
        'Netherlands', 'Belgium', 'Germany', 'France', 'United Kingdom',
        'Spain', 'Italy', 'Portugal', 'Denmark', 'Sweden', 'Norway',
        'Finland', 'Ireland', 'Switzerland', 'Austria', 'Poland',
        'Greece', 'Croatia', 'Malta', 'Cyprus', 'Luxembourg',
        'United States', 'Panama', 'Cayman Islands', 'Marshall Islands',
        'Monaco', 'Turkey', 'Montenegro',
    ];

    /**
     * These already exist as BoatField rows via BoatFieldSeeder — just flip
     * their governance flags on.
     */
    private const EXISTING_GOVERNED_FIELDS = [
        'builder',
        'designer',
        'engine_manufacturer',
        'engine_model',
        'fuel',
        'hull_construction',
    ];

    public function run(): void
    {
        $fuzzyMatcher = app(CopilotFuzzyMatcher::class);

        foreach (self::IDENTITY_FIELDS as $internalKey => [$relation, $column, $label]) {
            BoatField::query()->updateOrCreate(
                ['internal_key' => $internalKey],
                [
                    'labels_json' => ['en' => $label, 'nl' => $label, 'de' => $label, 'fr' => $label],
                    'field_type' => 'text',
                    'block_key' => 'identity',
                    'step_key' => 'identity',
                    'sort_order' => 0,
                    'storage_relation' => $relation,
                    'storage_column' => $column,
                    'ai_relevance' => true,
                    'is_active' => true,
                    'enable_autocomplete' => true,
                    'value_source' => 'database',
                    'allow_new_values' => true,
                    'allow_inline_archive' => true,
                    'fuzzy_matching' => true,
                    'is_required' => in_array($internalKey, ['manufacturer', 'model', 'boat_type'], true),
                    'is_searchable' => true,
                ],
            );

            $this->backfillFromYachts($internalKey, $relation, $column, $fuzzyMatcher);
        }

        $this->linkModelsToManufacturers($fuzzyMatcher);
        $this->seedStarterCountries($fuzzyMatcher);

        foreach (self::EXISTING_GOVERNED_FIELDS as $internalKey) {
            $field = BoatField::query()->where('internal_key', $internalKey)->first();
            if (! $field) {
                continue;
            }

            $field->update([
                'enable_autocomplete' => true,
                'value_source' => 'database',
                'allow_new_values' => true,
                'allow_inline_archive' => true,
                'fuzzy_matching' => true,
            ]);

            $this->backfillFromYachts($internalKey, $field->storage_relation, $field->storage_column, $fuzzyMatcher);
        }
    }

    private function backfillFromYachts(
        string $fieldKey,
        ?string $relation,
        string $column,
        CopilotFuzzyMatcher $fuzzyMatcher,
    ): void {
        $table = $relation ? $this->relatedTable($relation) : 'yachts';
        if (! $table) {
            return;
        }

        $counts = DB::table($table)
            ->selectRaw("{$column} as raw_value, COUNT(*) as total")
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->pluck('total', 'raw_value');

        foreach ($counts as $rawValue => $total) {
            $value = trim((string) $rawValue);
            if ($value === '') {
                continue;
            }

            $normalized = $fuzzyMatcher->normalize($value);
            if ($normalized === '') {
                continue;
            }

            CatalogValue::query()->updateOrCreate(
                ['field_key' => $fieldKey, 'normalized_value' => $normalized],
                [
                    'value' => $value,
                    'usage_count' => (int) $total,
                    'status' => CatalogValue::STATUS_ACTIVE,
                    'created_via' => 'seed',
                ],
            );
        }
    }

    /**
     * Sets catalog_values(model).parent_value_id to the model's most common
     * manufacturer, so the wizard can narrow model suggestions to the
     * selected brand (CatalogValueService::search()'s parent filter is
     * inclusive of NULL, so models with no dominant manufacturer — or that
     * genuinely exist under several brands — simply stay unscoped rather
     * than being hidden).
     */
    private function linkModelsToManufacturers(CopilotFuzzyMatcher $fuzzyMatcher): void
    {
        $pairCounts = DB::table('yachts')
            ->selectRaw('model, manufacturer, COUNT(*) as total')
            ->whereNotNull('model')->where('model', '!=', '')
            ->whereNotNull('manufacturer')->where('manufacturer', '!=', '')
            ->groupBy('model', 'manufacturer')
            ->get();

        if ($pairCounts->isEmpty()) {
            return;
        }

        $dominantManufacturerByModel = [];
        foreach ($pairCounts as $row) {
            $normalizedModel = $fuzzyMatcher->normalize((string) $row->model);
            if ($normalizedModel === '') {
                continue;
            }

            $existing = $dominantManufacturerByModel[$normalizedModel] ?? null;
            if (! $existing || $row->total > $existing['total']) {
                $dominantManufacturerByModel[$normalizedModel] = [
                    'manufacturer' => (string) $row->manufacturer,
                    'total' => (int) $row->total,
                ];
            }
        }

        $manufacturerIdsByNormalized = CatalogValue::query()
            ->forField('manufacturer')
            ->pluck('id', 'normalized_value');

        foreach ($dominantManufacturerByModel as $normalizedModel => $winner) {
            $normalizedManufacturer = $fuzzyMatcher->normalize($winner['manufacturer']);
            $manufacturerId = $manufacturerIdsByNormalized[$normalizedManufacturer] ?? null;
            if (! $manufacturerId) {
                continue;
            }

            CatalogValue::query()
                ->forField('model')
                ->where('normalized_value', $normalizedModel)
                ->update(['parent_value_id' => $manufacturerId]);
        }
    }

    private function seedStarterCountries(CopilotFuzzyMatcher $fuzzyMatcher): void
    {
        foreach (self::STARTER_COUNTRIES as $country) {
            $normalized = $fuzzyMatcher->normalize($country);
            if ($normalized === '') {
                continue;
            }

            CatalogValue::query()->firstOrCreate(
                ['field_key' => 'location_country', 'normalized_value' => $normalized],
                [
                    'value' => $country,
                    'usage_count' => 0,
                    'status' => CatalogValue::STATUS_ACTIVE,
                    'created_via' => 'seed',
                ],
            );
        }
    }

    private function relatedTable(string $relation): ?string
    {
        if (! method_exists(Yacht::class, $relation)) {
            return null;
        }

        return (new Yacht())->{$relation}()->getRelated()->getTable();
    }
}
