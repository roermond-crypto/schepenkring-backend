<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ─── Column lists per sub-table ───────────────────────────────

    private array $dimensionCols = [
        'beam', 'draft', 'loa', 'lwl', 'air_draft', 'displacement',
        'ballast', 'passenger_capacity', 'minimum_height', 'variable_depth',
        'max_draft', 'min_draft',
    ];

    private array $constructionCols = [
        'designer', 'builder', 'where', 'hull_colour', 'hull_construction',
        'hull_number', 'hull_type', 'super_structure_colour',
        'super_structure_construction', 'deck_colour', 'deck_construction',
        'windows', 'cockpit_type', 'control_type', 'flybridge',
    ];

    private array $accommodationCols = [
        'cabins', 'berths', 'toilet', 'shower', 'bath',
        'interior_type', 'saloon', 'headroom', 'separate_dining_area',
        'engine_room', 'spaces_inside', 'upholstery_color', 'matrasses',
        'cushions', 'curtains', 'berths_fixed', 'berths_extra', 'berths_crew',
    ];

    private array $engineCols = [
        'engine_manufacturer', 'horse_power', 'hours', 'fuel', 'max_speed',
        'cruising_speed', 'engine_quantity', 'tankage', 'gallons_per_hour',
        'litres_per_hour', 'engine_location', 'gearbox', 'cylinders',
        'propeller_type', 'starting_type', 'drive_type', 'cooling_system',
        'engine_model', 'engine_serial_number', 'engine_type', 'engine_year',
        'reversing_clutch', 'transmission', 'propulsion', 'motorization_summary',
        'fuel_tanks_amount', 'fuel_tank_total_capacity', 'fuel_tank_material',
        'range_km', 'stern_thruster', 'bow_thruster',
    ];

    private array $electricalCols = [
        'battery', 'battery_charger', 'generator', 'inverter',
        'dynamo', 'accumonitor', 'voltmeter', 'shorepower', 'shore_power_cable',
        'wind_generator', 'solar_panel', 'consumption_monitor', 'control_panel',
        'voltage', 'fuel_tank_gauge', 'tachometer', 'oil_pressure_gauge',
        'temperature_gauge',
    ];

    private array $navigationCols = [
        'navigation_lights', 'compass', 'depth_instrument', 'wind_instrument',
        'autopilot', 'gps', 'vhf', 'plotter', 'speed_instrument', 'radar',
        'log_speed', 'windvane_steering', 'charts_guides',
        'rudder_position_indicator', 'fishfinder', 'turn_indicator', 'ais',
        'ssb_receiver', 'shortwave_radio', 'short_band_transmitter',
        'weatherfax_navtex', 'satellite_communication',
    ];

    private array $safetyCols = [
        'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher', 'mob_system',
        'life_buoy', 'bilge_pump_manual', 'bilge_pump_electric',
        'radar_reflector', 'flares', 'life_jackets', 'watertight_door',
        'gas_bottle_locker', 'self_draining_cockpit',
    ];

    private array $comfortCols = [
        'oven', 'microwave', 'fridge', 'freezer', 'heating', 'air_conditioning',
        'cooker', 'cooking_fuel', 'hot_air', 'stove', 'central_heating',
        'satellite_reception', 'television', 'cd_player', 'dvd_player',
        'water_tank', 'water_tank_material', 'water_tank_gauge', 'water_maker',
        'waste_water_tank', 'waste_water_tank_material', 'waste_water_tank_gauge',
        'waste_water_tank_drainpump', 'deck_suction', 'water_system', 'hot_water',
        'sea_water_pump', 'deck_wash_pump', 'deck_shower',
    ];

    private array $deckEquipmentCols = [
        'anchor', 'spray_hood', 'bimini', 'fenders',
        'anchor_connection', 'anchor_winch', 'stern_anchor', 'spud_pole',
        'cockpit_tent', 'outdoor_cushions', 'covers', 'sea_rails',
        'pushpit_pullpit', 'swimming_platform', 'swimming_ladder',
        'sail_lowering_system', 'crutch', 'dinghy', 'dinghy_brand',
        'outboard_engine', 'trailer', 'crane', 'davits', 'teak_deck',
        'cockpit_table', 'oars_paddles',
    ];

    private array $riggingCols = [
        'sailplan_type', 'number_of_masts', 'spars_material', 'bowsprit',
        'standing_rig', 'sail_surface_area', 'stabilizer_sail', 'sail_amount',
        'sail_material', 'sail_manufacturer', 'genoa', 'main_sail',
        'furling_mainsail', 'tri_sail', 'storm_jib', 'mizzen', 'furling_mizzen',
        'jib', 'roller_furling_foresail', 'genoa_reefing_system', 'flying_jib',
        'spinnaker', 'halfwinder_bollejan', 'gennaker', 'winches',
        'electric_winches', 'manual_winches', 'hydraulic_winches',
        'self_tailing_winches',
    ];

    // ─── UP ──────────────────────────────────────────────────────

    public function up(): void
    {
        // 1. Create all sub-tables (all columns nullable TEXT to match current schema)
        $this->createSubTable('yacht_dimensions', $this->dimensionCols);
        $this->createSubTable('yacht_construction', $this->constructionCols);
        $this->createSubTable('yacht_accommodation', $this->accommodationCols);
        $this->createSubTable('yacht_engines', $this->engineCols);
        $this->createSubTable('yacht_electrical', $this->electricalCols);
        $this->createSubTable('yacht_navigation', $this->navigationCols);
        $this->createSubTable('yacht_safety', $this->safetyCols);
        $this->createSubTable('yacht_comfort', $this->comfortCols);
        $this->createSubTable('yacht_deck_equipment', $this->deckEquipmentCols);
        $this->createSubTable('yacht_rigging', $this->riggingCols);

        // 2. Copy data from yachts → sub-tables
        $this->copyData('yacht_dimensions', $this->dimensionCols);
        $this->copyData('yacht_construction', $this->constructionCols);
        $this->copyData('yacht_accommodation', $this->accommodationCols);
        $this->copyData('yacht_engines', $this->engineCols);
        $this->copyData('yacht_electrical', $this->electricalCols);
        $this->copyData('yacht_navigation', $this->navigationCols);
        $this->copyData('yacht_safety', $this->safetyCols);
        $this->copyData('yacht_comfort', $this->comfortCols);
        $this->copyData('yacht_deck_equipment', $this->deckEquipmentCols);
        $this->copyData('yacht_rigging', $this->riggingCols);

        // 3. Drop moved columns from yachts (only columns that actually exist)
        $allMovedCols = array_merge(
            $this->dimensionCols,
            $this->constructionCols,
            $this->accommodationCols,
            $this->engineCols,
            $this->electricalCols,
            $this->navigationCols,
            $this->safetyCols,
            $this->comfortCols,
            $this->deckEquipmentCols,
            $this->riggingCols,
        );

        Schema::table('yachts', function (Blueprint $table) use ($allMovedCols) {
            $existingCols = [];
            foreach ($allMovedCols as $col) {
                if (Schema::hasColumn('yachts', $col)) {
                    $existingCols[] = $col;
                }
            }
            if (!empty($existingCols)) {
                $table->dropColumn($existingCols);
            }
        });
    }

    // ─── DOWN ────────────────────────────────────────────────────

    public function down(): void
    {
        // Re-add all columns to yachts
        $allSets = [
            $this->dimensionCols,
            $this->constructionCols,
            $this->accommodationCols,
            $this->engineCols,
            $this->electricalCols,
            $this->navigationCols,
            $this->safetyCols,
            $this->comfortCols,
            $this->deckEquipmentCols,
            $this->riggingCols,
        ];

        Schema::table('yachts', function (Blueprint $table) use ($allSets) {
            foreach ($allSets as $cols) {
                foreach ($cols as $col) {
                    if (!Schema::hasColumn('yachts', $col)) {
                        $table->text($col)->nullable();
                    }
                }
            }
        });

        // Copy data back
        $subTables = [
            'yacht_dimensions'    => $this->dimensionCols,
            'yacht_construction'  => $this->constructionCols,
            'yacht_accommodation' => $this->accommodationCols,
            'yacht_engines'       => $this->engineCols,
            'yacht_electrical'    => $this->electricalCols,
            'yacht_navigation'    => $this->navigationCols,
            'yacht_safety'        => $this->safetyCols,
            'yacht_comfort'       => $this->comfortCols,
            'yacht_deck_equipment'=> $this->deckEquipmentCols,
            'yacht_rigging'       => $this->riggingCols,
        ];

        foreach ($subTables as $table => $cols) {
            $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
            $setClause = implode(', ', array_map(fn($c) => "`yachts`.`$c` = `$table`.`$c`", $cols));

            DB::statement("
                UPDATE `yachts`
                INNER JOIN `$table` ON `$table`.`yacht_id` = `yachts`.`id`
                SET $setClause
            ");
        }

        // Drop sub-tables
        foreach (array_keys($subTables) as $table) {
            Schema::dropIfExists($table);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function createSubTable(string $tableName, array $columns): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($columns) {
            $table->id();
            $table->foreignId('yacht_id')->constrained()->onDelete('cascade');

            foreach ($columns as $col) {
                $table->text($col)->nullable();
            }

            // Unique constraint ensures one-to-one
            $table->unique('yacht_id');
        });
    }

    private function copyData(string $targetTable, array $columns): void
    {
        // Only copy columns that exist on the yachts table
        $existingCols = [];
        foreach ($columns as $col) {
            if (Schema::hasColumn('yachts', $col)) {
                $existingCols[] = $col;
            }
        }

        if (empty($existingCols)) {
            // Still create empty rows for each yacht so relationships work
            DB::statement("
                INSERT INTO `$targetTable` (`yacht_id`)
                SELECT `id` FROM `yachts`
            ");
            return;
        }

        $colList = implode(', ', array_map(fn($c) => "`$c`", $existingCols));

        DB::statement("
            INSERT INTO `$targetTable` (`yacht_id`, $colList)
            SELECT `id`, $colList FROM `yachts`
        ");
    }
};
