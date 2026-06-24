<?php

namespace App\Services;

use App\Models\Yacht;
use App\Models\YachtImage;
use App\Models\BoatDocument;
use Illuminate\Support\Facades\DB;

class YachtCompletenessService
{
    // Dimension weights (must sum to 100)
    private const WEIGHTS = [
        'basic_data'   => 20,
        'required'     => 25,
        'description'  => 15,
        'images'       => 20,
        'specs'        => 12,
        'video'        => 5,
        'documents'    => 3,
    ];

    // Step-1 "basic data" fields — the most important identity fields
    private const BASIC_FIELDS = [
        'boat_name', 'manufacturer', 'model', 'year', 'price',
        'boat_type', 'boat_category', 'new_or_used', 'status', 'vessel_lying',
    ];
    private const BASIC_FIELDS_TARGET = 10; // same as array count above

    // Required fields for publishing — missing any blocks publish
    private const REQUIRED_FIELDS = [
        'boat_name', 'manufacturer', 'model', 'year', 'price',
        'boat_type', 'status', 'loa', 'beam', 'engine_manufacturer',
        'horse_power', 'fuel',
    ];
    private const REQUIRED_FIELDS_TARGET = 12;

    // Description target
    private const DESC_TARGET = 1200;

    // Image targets
    private const IMAGES_TARGET = 50;

    // Specification fields across all sub-tables (used for counting filled specs)
    private const SPEC_FIELDS = [
        // Dimensions
        'loa', 'lwl', 'beam', 'draft', 'air_draft', 'displacement', 'ballast',
        'passenger_capacity', 'minimum_height', 'max_draft', 'min_draft',
        // Construction
        'designer', 'builder', 'hull_colour', 'hull_construction', 'hull_number',
        'hull_type', 'super_structure_colour', 'deck_construction', 'flybridge',
        // Accommodation
        'cabins', 'berths', 'toilet', 'shower', 'interior_type', 'headroom',
        // Engine
        'engine_manufacturer', 'engine_model', 'engine_type', 'horse_power',
        'hours', 'fuel', 'max_speed', 'cruising_speed', 'engine_quantity',
        'engine_year', 'tankage', 'drive_type', 'propulsion', 'gearbox',
        'cylinders', 'propeller_type', 'cooling_system', 'bow_thruster', 'stern_thruster',
        // Electrical
        'battery', 'generator', 'inverter', 'shorepower', 'solar_panel', 'voltage',
        // Navigation
        'compass', 'depth_instrument', 'wind_instrument', 'autopilot', 'gps',
        'vhf', 'plotter', 'speed_instrument', 'radar', 'ais',
        // Safety
        'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher', 'life_buoy',
        'life_jackets', 'flares',
        // Comfort
        'fridge', 'heating', 'air_conditioning', 'cooker', 'hot_water',
        'water_tank', 'waste_water_tank', 'television',
        // Deck
        'anchor', 'spray_hood', 'bimini', 'swimming_platform', 'swimming_ladder',
        'dinghy', 'teak_deck',
        // Rigging (for sailing boats)
        'sailplan_type', 'spars_material', 'main_sail', 'genoa',
    ];
    private const SPEC_FIELDS_TARGET = 150; // approximate total fields in system

    // Document target
    private const DOCS_TARGET = 3;
    // Video target
    private const VIDEO_TARGET = 1;

    /**
     * Calculate and persist completeness score for a yacht.
     */
    public function calculate(Yacht $yacht): array
    {
        $flat = $this->flattenYacht($yacht);

        $dimensions = [
            'basic_data'  => $this->scoreBasicData($flat),
            'required'    => $this->scoreRequired($flat),
            'description' => $this->scoreDescription($flat),
            'images'      => $this->scoreImages($yacht->id),
            'specs'       => $this->scoreSpecs($flat),
            'video'       => $this->scoreVideo($yacht->id),
            'documents'   => $this->scoreDocs($yacht->id),
        ];

        $totalScore = 0.0;
        foreach ($dimensions as $key => $dim) {
            $totalScore += ($dim['pct'] / 100) * self::WEIGHTS[$key];
        }
        $totalScore = round($totalScore, 1);

        $breakdown = [
            'total'      => $totalScore,
            'dimensions' => $dimensions,
            'weights'    => self::WEIGHTS,
        ];

        $yacht->forceFill([
            'completeness_score'          => $totalScore,
            'completeness_breakdown'      => $breakdown,
            'completeness_calculated_at'  => now(),
        ])->save();

        return $breakdown;
    }

    /**
     * Return score from cache or calculate fresh.
     */
    public function get(Yacht $yacht): array
    {
        if ($yacht->completeness_breakdown && $yacht->completeness_calculated_at) {
            return is_array($yacht->completeness_breakdown)
                ? $yacht->completeness_breakdown
                : json_decode($yacht->completeness_breakdown, true) ?? [];
        }

        return $this->calculate($yacht);
    }

    /**
     * Check which required fields are missing (used before publish).
     */
    public function missingRequired(Yacht $yacht): array
    {
        $flat = $this->flattenYacht($yacht);
        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            if ($this->isEmpty($flat[$field] ?? null)) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    // ── Private calculation helpers ──────────────────────────────

    private function scoreBasicData(array $flat): array
    {
        $filled = 0;
        foreach (self::BASIC_FIELDS as $field) {
            if (!$this->isEmpty($flat[$field] ?? null)) {
                $filled++;
            }
        }
        $pct = round($filled / self::BASIC_FIELDS_TARGET * 100, 1);
        return ['filled' => $filled, 'total' => self::BASIC_FIELDS_TARGET, 'pct' => $pct];
    }

    private function scoreRequired(array $flat): array
    {
        $filled = 0;
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!$this->isEmpty($flat[$field] ?? null)) {
                $filled++;
            }
        }
        $pct = round($filled / self::REQUIRED_FIELDS_TARGET * 100, 1);
        return ['filled' => $filled, 'total' => self::REQUIRED_FIELDS_TARGET, 'pct' => $pct];
    }

    private function scoreDescription(array $flat): array
    {
        $desc = $flat['short_description_nl'] ?? $flat['owners_comment'] ?? '';
        $len  = mb_strlen((string) $desc);
        $pct  = round(min($len / self::DESC_TARGET * 100, 100), 1);
        return ['chars' => $len, 'target' => self::DESC_TARGET, 'pct' => $pct];
    }

    private function scoreImages(int|string $yachtId): array
    {
        $count = YachtImage::where('yacht_id', $yachtId)->count();
        $pct   = round(min($count / self::IMAGES_TARGET * 100, 100), 1);
        return ['count' => $count, 'target' => self::IMAGES_TARGET, 'pct' => $pct];
    }

    private function scoreSpecs(array $flat): array
    {
        $filled = 0;
        foreach (self::SPEC_FIELDS as $field) {
            if (!$this->isEmpty($flat[$field] ?? null)) {
                $filled++;
            }
        }
        $pct = round($filled / self::SPEC_FIELDS_TARGET * 100, 1);
        return ['filled' => $filled, 'total' => self::SPEC_FIELDS_TARGET, 'pct' => $pct];
    }

    private function scoreVideo(int|string $yachtId): array
    {
        $rendered = DB::table('video_plans')
            ->where('yacht_id', $yachtId)
            ->where('status', 'rendered')
            ->exists();

        $queued = !$rendered && DB::table('video_plans')
            ->where('yacht_id', $yachtId)
            ->whereIn('status', ['generating', 'approved', 'rendering'])
            ->exists();

        $pct = $rendered ? 100.0 : 0.0;
        return [
            'count'   => (int) $rendered,
            'target'  => self::VIDEO_TARGET,
            'status'  => $rendered ? 'ready' : ($queued ? 'in_progress' : 'not_created'),
            'pct'     => $pct,
        ];
    }

    private function scoreDocs(int|string $yachtId): array
    {
        $count = DB::table('boat_documents')->where('yacht_id', $yachtId)->count();
        $pct   = round(min($count / self::DOCS_TARGET * 100, 100), 1);
        return ['count' => $count, 'target' => self::DOCS_TARGET, 'pct' => $pct];
    }

    /**
     * Flatten all eager-loaded sub-tables into a single key→value array.
     */
    private function flattenYacht(Yacht $yacht): array
    {
        $yacht->loadMissing([
            'dimensions', 'construction', 'accommodation', 'engine',
            'electrical', 'navigation', 'safety', 'comfort', 'deckEquipment', 'rigging',
        ]);

        $flat = $yacht->toArray();

        $relations = [
            'dimensions', 'construction', 'accommodation', 'engine',
            'electrical', 'navigation', 'safety', 'comfort', 'deck_equipment', 'rigging',
        ];

        foreach ($relations as $rel) {
            if (isset($flat[$rel]) && is_array($flat[$rel])) {
                $flat = array_merge($flat, $flat[$rel]);
                unset($flat[$rel]);
            }
        }

        return $flat;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }
        if (is_string($value) && in_array(strtolower(trim($value)), ['null', 'unknown', 'n/a', '-'], true)) {
            return true;
        }
        return false;
    }
}
