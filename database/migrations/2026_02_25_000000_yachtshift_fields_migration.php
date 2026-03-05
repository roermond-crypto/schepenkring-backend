<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────
        // STEP 1: Change boolean columns → varchar(100)
        // These were checkboxes but Yachtshift sends
        // text data (brand names, quantities, etc.)
        // Using varchar(100) to save row space.
        // ─────────────────────────────────────────────
        $boolToString = [
            'compass', 'depth_instrument', 'wind_instrument', 'navigation_lights',
            'autopilot', 'gps', 'plotter', 'radar', 'speed_instrument', 'vhf',
            'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher', 'mob_system',
            'spinnaker', 'battery', 'battery_charger', 'generator', 'inverter',
            'television', 'cd_player', 'dvd_player', 'oven', 'microwave',
            'fridge', 'freezer', 'anchor', 'spray_hood', 'bimini',
        ];

        foreach ($boolToString as $col) {
            Schema::table('yachts', function (Blueprint $table) use ($col) {
                $table->string($col, 100)->nullable()->change();
            });
        }

        // ─────────────────────────────────────────────
        // STEP 2: Add new columns from Yachtshift feed
        // Using TEXT type to avoid MySQL row size limit.
        // ─────────────────────────────────────────────
        Schema::table('yachts', function (Blueprint $table) {
            // General / Identity
            $table->text('boat_type')->nullable();
            $table->text('boat_category')->nullable();
            $table->text('new_or_used')->nullable();
            $table->text('manufacturer')->nullable();
            $table->text('model')->nullable();
            $table->text('vessel_lying')->nullable();
            $table->text('location_city')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->text('short_description_nl')->nullable();
            $table->text('short_description_en')->nullable();
            $table->text('motorization_summary')->nullable();
            $table->text('advertise_as')->nullable();

            // CE Certification
            $table->text('ce_category')->nullable();
            $table->text('ce_max_weight')->nullable();
            $table->text('ce_max_motor')->nullable();
            $table->text('cvo')->nullable();
            $table->text('cbb')->nullable();

            // Hull & Structure
            $table->text('windows')->nullable();
            $table->text('open_cockpit')->nullable();
            $table->text('aft_cockpit')->nullable();
            $table->text('minimum_height')->nullable();
            $table->text('variable_depth')->nullable();
            $table->text('max_draft')->nullable();
            $table->text('min_draft')->nullable();
            $table->text('ballast_tank')->nullable();
            $table->text('steering_system')->nullable();
            $table->text('steering_system_location')->nullable();
            $table->text('remote_control')->nullable();
            $table->text('rudder')->nullable();
            $table->text('drift_restriction')->nullable();
            $table->text('drift_restriction_controls')->nullable();
            $table->text('trimflaps')->nullable();
            $table->text('stabilizer')->nullable();
        });

        Schema::table('yachts', function (Blueprint $table) {
            // Accommodation / Interior
            $table->text('interior_type')->nullable();
            $table->text('saloon')->nullable();
            $table->text('headroom')->nullable();
            $table->text('separate_dining_area')->nullable();
            $table->text('engine_room')->nullable();
            $table->text('spaces_inside')->nullable();
            $table->text('upholstery_color')->nullable();
            $table->text('matrasses')->nullable();
            $table->text('cushions')->nullable();
            $table->text('curtains')->nullable();
            $table->text('berths_fixed')->nullable();
            $table->text('berths_extra')->nullable();
            $table->text('berths_crew')->nullable();

            // Water System
            $table->text('water_tank')->nullable();
            $table->text('water_tank_material')->nullable();
            $table->text('water_tank_gauge')->nullable();
            $table->text('water_maker')->nullable();
            $table->text('waste_water_tank')->nullable();
            $table->text('waste_water_tank_material')->nullable();
            $table->text('waste_water_tank_gauge')->nullable();
            $table->text('waste_water_tank_drainpump')->nullable();
            $table->text('deck_suction')->nullable();
            $table->text('water_system')->nullable();
            $table->text('hot_water')->nullable();
            $table->text('sea_water_pump')->nullable();
            $table->text('deck_wash_pump')->nullable();
            $table->text('deck_shower')->nullable();

            // Kitchen / Comfort
            $table->text('cooker')->nullable();
            $table->text('cooking_fuel')->nullable();
            $table->text('hot_air')->nullable();
            $table->text('stove')->nullable();
            $table->text('central_heating')->nullable();
            $table->text('satellite_reception')->nullable();
        });

        Schema::table('yachts', function (Blueprint $table) {
            // Engine & Propulsion
            $table->text('engine_model')->nullable();
            $table->text('engine_serial_number')->nullable();
            $table->text('engine_type')->nullable();
            $table->text('engine_year')->nullable();
            $table->text('reversing_clutch')->nullable();
            $table->text('transmission')->nullable();
            $table->text('propulsion')->nullable();
            $table->text('fuel_tanks_amount')->nullable();
            $table->text('fuel_tank_total_capacity')->nullable();
            $table->text('fuel_tank_material')->nullable();
            $table->text('range_km')->nullable();

            // Engine Instruments
            $table->text('fuel_tank_gauge')->nullable();
            $table->text('tachometer')->nullable();
            $table->text('oil_pressure_gauge')->nullable();
            $table->text('temperature_gauge')->nullable();

            // Electrical System
            $table->text('dynamo')->nullable();
            $table->text('accumonitor')->nullable();
            $table->text('voltmeter')->nullable();
            $table->text('shorepower')->nullable();
            $table->text('shore_power_cable')->nullable();
            $table->text('wind_generator')->nullable();
            $table->text('solar_panel')->nullable();
            $table->text('consumption_monitor')->nullable();
            $table->text('control_panel')->nullable();
            $table->text('voltage')->nullable();
        });

        Schema::table('yachts', function (Blueprint $table) {
            // Navigation
            $table->text('log_speed')->nullable();
            $table->text('windvane_steering')->nullable();
            $table->text('charts_guides')->nullable();
            $table->text('rudder_position_indicator')->nullable();
            $table->text('fishfinder')->nullable();
            $table->text('turn_indicator')->nullable();
            $table->text('ais')->nullable();
            $table->text('ssb_receiver')->nullable();
            $table->text('shortwave_radio')->nullable();
            $table->text('short_band_transmitter')->nullable();
            $table->text('weatherfax_navtex')->nullable();
            $table->text('satellite_communication')->nullable();

            // Rigging / Sails
            $table->text('sailplan_type')->nullable();
            $table->text('number_of_masts')->nullable();
            $table->text('spars_material')->nullable();
            $table->text('bowsprit')->nullable();
            $table->text('standing_rig')->nullable();
            $table->text('sail_surface_area')->nullable();
            $table->text('stabilizer_sail')->nullable();
            $table->text('sail_amount')->nullable();
            $table->text('sail_material')->nullable();
            $table->text('sail_manufacturer')->nullable();
            $table->text('furling_mainsail')->nullable();
            $table->text('mizzen')->nullable();
            $table->text('furling_mizzen')->nullable();
            $table->text('jib')->nullable();
            $table->text('roller_furling_foresail')->nullable();
            $table->text('genoa_reefing_system')->nullable();
            $table->text('flying_jib')->nullable();
            $table->text('halfwinder_bollejan')->nullable();
            $table->text('gennaker')->nullable();
            $table->text('electric_winches')->nullable();
            $table->text('manual_winches')->nullable();
            $table->text('hydraulic_winches')->nullable();
            $table->text('self_tailing_winches')->nullable();
        });

        Schema::table('yachts', function (Blueprint $table) {
            // Deck Equipment
            $table->text('anchor_connection')->nullable();
            $table->text('anchor_winch')->nullable();
            $table->text('stern_anchor')->nullable();
            $table->text('spud_pole')->nullable();
            $table->text('cockpit_tent')->nullable();
            $table->text('outdoor_cushions')->nullable();
            $table->text('covers')->nullable();
            $table->text('sea_rails')->nullable();
            $table->text('pushpit_pullpit')->nullable();
            $table->text('swimming_platform')->nullable();
            $table->text('swimming_ladder')->nullable();
            $table->text('sail_lowering_system')->nullable();
            $table->text('crutch')->nullable();
            $table->text('dinghy')->nullable();
            $table->text('dinghy_brand')->nullable();
            $table->text('outboard_engine')->nullable();
            $table->text('trailer')->nullable();
            $table->text('crane')->nullable();
            $table->text('davits')->nullable();
            $table->text('teak_deck')->nullable();
            $table->text('cockpit_table')->nullable();
            $table->text('oars_paddles')->nullable();

            // Safety
            $table->text('life_buoy')->nullable();
            $table->text('bilge_pump_manual')->nullable();
            $table->text('bilge_pump_electric')->nullable();
            $table->text('radar_reflector')->nullable();
            $table->text('flares')->nullable();
            $table->text('life_jackets')->nullable();
            $table->text('watertight_door')->nullable();
            $table->text('gas_bottle_locker')->nullable();
            $table->text('self_draining_cockpit')->nullable();
        });
    }

    public function down(): void
    {
        // Revert boolean columns
        $stringToBool = [
            'compass', 'depth_instrument', 'wind_instrument', 'navigation_lights',
            'autopilot', 'gps', 'plotter', 'radar', 'speed_instrument', 'vhf',
            'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher', 'mob_system',
            'spinnaker', 'battery', 'battery_charger', 'generator', 'inverter',
            'television', 'cd_player', 'dvd_player', 'oven', 'microwave',
            'fridge', 'freezer', 'anchor', 'spray_hood', 'bimini',
        ];

        foreach ($stringToBool as $col) {
            Schema::table('yachts', function (Blueprint $table) use ($col) {
                $table->boolean($col)->nullable()->change();
            });
        }

        // Drop new columns
        $dropCols = [
            'boat_type', 'boat_category', 'new_or_used', 'manufacturer', 'model',
            'vessel_lying', 'location_city', 'location_lat', 'location_lng',
            'short_description_nl', 'short_description_en', 'motorization_summary', 'advertise_as',
            'ce_category', 'ce_max_weight', 'ce_max_motor', 'cvo', 'cbb',
            'windows', 'open_cockpit', 'aft_cockpit', 'minimum_height', 'variable_depth',
            'max_draft', 'min_draft', 'ballast_tank', 'steering_system', 'steering_system_location',
            'remote_control', 'rudder', 'drift_restriction', 'drift_restriction_controls', 'trimflaps', 'stabilizer',
            'interior_type', 'saloon', 'headroom', 'separate_dining_area', 'engine_room', 'spaces_inside',
            'upholstery_color', 'matrasses', 'cushions', 'curtains', 'berths_fixed', 'berths_extra', 'berths_crew',
            'water_tank', 'water_tank_material', 'water_tank_gauge', 'water_maker',
            'waste_water_tank', 'waste_water_tank_material', 'waste_water_tank_gauge',
            'waste_water_tank_drainpump', 'deck_suction', 'water_system', 'hot_water', 'sea_water_pump',
            'deck_wash_pump', 'deck_shower',
            'cooker', 'cooking_fuel', 'hot_air', 'stove', 'central_heating', 'satellite_reception',
            'engine_model', 'engine_serial_number', 'engine_type', 'engine_year',
            'reversing_clutch', 'transmission', 'propulsion',
            'fuel_tanks_amount', 'fuel_tank_total_capacity', 'fuel_tank_material', 'range_km',
            'fuel_tank_gauge', 'tachometer', 'oil_pressure_gauge', 'temperature_gauge',
            'dynamo', 'accumonitor', 'voltmeter', 'shorepower', 'shore_power_cable',
            'wind_generator', 'solar_panel', 'consumption_monitor', 'control_panel', 'voltage',
            'log_speed', 'windvane_steering', 'charts_guides', 'rudder_position_indicator',
            'fishfinder', 'turn_indicator', 'ais', 'ssb_receiver', 'shortwave_radio',
            'short_band_transmitter', 'weatherfax_navtex', 'satellite_communication',
            'sailplan_type', 'number_of_masts', 'spars_material', 'bowsprit', 'standing_rig',
            'sail_surface_area', 'stabilizer_sail', 'sail_amount', 'sail_material', 'sail_manufacturer',
            'furling_mainsail', 'mizzen', 'furling_mizzen', 'jib', 'roller_furling_foresail',
            'genoa_reefing_system', 'flying_jib', 'halfwinder_bollejan', 'gennaker',
            'electric_winches', 'manual_winches', 'hydraulic_winches', 'self_tailing_winches',
            'anchor_connection', 'anchor_winch', 'stern_anchor', 'spud_pole',
            'cockpit_tent', 'outdoor_cushions', 'covers', 'sea_rails', 'pushpit_pullpit',
            'swimming_platform', 'swimming_ladder', 'sail_lowering_system', 'crutch',
            'dinghy', 'dinghy_brand', 'outboard_engine', 'trailer', 'crane', 'davits', 'teak_deck',
            'cockpit_table', 'oars_paddles',
            'life_buoy', 'bilge_pump_manual', 'bilge_pump_electric', 'radar_reflector', 'flares',
            'life_jackets', 'watertight_door', 'gas_bottle_locker', 'self_draining_cockpit',
        ];

        Schema::table('yachts', function (Blueprint $table) use ($dropCols) {
            $table->dropColumn($dropCols);
        });
    }
};
