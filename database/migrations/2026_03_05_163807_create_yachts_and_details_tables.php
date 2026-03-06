<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('yachts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Core identity
            $table->string('vessel_id')->unique();
            $table->string('boat_name')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('status')->default('Draft');
            $table->boolean('allow_bidding')->default(false);
            $table->string('main_image')->nullable();
            $table->integer('year')->nullable();
            $table->decimal('min_bid_amount', 15, 2)->nullable();
            $table->decimal('current_bid', 15, 2)->nullable();
            $table->foreignId('boat_type_id')->nullable();
            $table->boolean('display_specs')->default(true);
            $table->uuid('offline_uuid')->nullable();
            $table->integer('booking_duration_minutes')->nullable();

            // Identity (from Yachtshift)
            $table->string('boat_type')->nullable();
            $table->string('boat_category')->nullable();
            $table->string('new_or_used')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('vessel_lying')->nullable();
            $table->string('location_city')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->text('short_description_nl')->nullable();
            $table->text('short_description_en')->nullable();
            $table->text('short_description_de')->nullable();
            $table->string('advertise_as')->nullable();
            $table->json('advertising_channels')->nullable();

            // URLs and references
            $table->string('external_url')->nullable();
            $table->string('print_url')->nullable();
            $table->text('owners_comment')->nullable();
            $table->text('reg_details')->nullable();
            $table->text('known_defects')->nullable();
            $table->string('last_serviced')->nullable();

            // CE Certification
            $table->string('ce_category')->nullable();
            $table->string('ce_max_weight')->nullable();
            $table->string('ce_max_motor')->nullable();
            $table->string('cvo')->nullable();
            $table->string('cbb')->nullable();

            // Hull & Structure (remaining on yachts)
            $table->string('open_cockpit')->nullable();
            $table->string('aft_cockpit')->nullable();
            $table->string('ballast_tank')->nullable();
            $table->string('steering_system')->nullable();
            $table->string('steering_system_location')->nullable();
            $table->string('remote_control')->nullable();
            $table->string('rudder')->nullable();
            $table->string('drift_restriction')->nullable();
            $table->string('drift_restriction_controls')->nullable();
            $table->string('trimflaps')->nullable();
            $table->string('stabilizer')->nullable();

            // Google Merchant Sync
            $table->string('google_offer_id')->nullable();
            $table->string('google_product_id')->nullable();
            $table->string('google_status')->nullable();
            $table->timestamp('google_last_sync_at')->nullable();
            $table->text('google_last_error')->nullable();

            // Attribution / Financial fields
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('commission_percentage', 5, 2)->nullable();
            $table->decimal('harbor_split_percentage', 5, 2)->nullable();
            $table->decimal('commission_amount', 15, 2)->nullable();
            $table->timestamp('commission_calculated_at')->nullable();
            $table->string('ref_code')->nullable();
            $table->foreignId('ref_harbor_id')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->timestamp('ref_captured_at')->nullable();
            $table->string('sale_stage')->nullable();

            $table->timestamps();
        });

        // 1. Create all sub-tables (all columns nullable TEXT to match current schema)
        $this->createSubTable('yacht_dimensions', [
            'beam', 'draft', 'loa', 'lwl', 'air_draft', 'displacement',
            'ballast', 'passenger_capacity', 'minimum_height', 'variable_depth',
            'max_draft', 'min_draft',
        ]);
        $this->createSubTable('yacht_construction', [
            'designer', 'builder', 'where', 'hull_colour', 'hull_construction',
            'hull_number', 'hull_type', 'super_structure_colour',
            'super_structure_construction', 'deck_colour', 'deck_construction',
            'windows', 'cockpit_type', 'control_type', 'flybridge',
        ]);
        $this->createSubTable('yacht_accommodation', [
            'cabins', 'berths', 'toilet', 'shower', 'bath',
            'interior_type', 'saloon', 'headroom', 'separate_dining_area',
            'engine_room', 'spaces_inside', 'upholstery_color', 'matrasses',
            'cushions', 'curtains', 'berths_fixed', 'berths_extra', 'berths_crew',
        ]);
        $this->createSubTable('yacht_engines', [
            'engine_manufacturer', 'horse_power', 'hours', 'fuel', 'max_speed',
            'cruising_speed', 'engine_quantity', 'tankage', 'gallons_per_hour',
            'litres_per_hour', 'engine_location', 'gearbox', 'cylinders',
            'propeller_type', 'starting_type', 'drive_type', 'cooling_system',
            'engine_model', 'engine_serial_number', 'engine_type', 'engine_year',
            'reversing_clutch', 'transmission', 'propulsion', 'motorization_summary',
            'fuel_tanks_amount', 'fuel_tank_total_capacity', 'fuel_tank_material',
            'range_km', 'stern_thruster', 'bow_thruster',
        ]);
        $this->createSubTable('yacht_electrical', [
            'battery', 'battery_charger', 'generator', 'inverter',
            'dynamo', 'accumonitor', 'voltmeter', 'shorepower', 'shore_power_cable',
            'wind_generator', 'solar_panel', 'consumption_monitor', 'control_panel',
            'voltage', 'fuel_tank_gauge', 'tachometer', 'oil_pressure_gauge',
            'temperature_gauge',
        ]);
        $this->createSubTable('yacht_navigation', [
            'navigation_lights', 'compass', 'depth_instrument', 'wind_instrument',
            'autopilot', 'gps', 'vhf', 'plotter', 'speed_instrument', 'radar',
            'log_speed', 'windvane_steering', 'charts_guides',
            'rudder_position_indicator', 'fishfinder', 'turn_indicator', 'ais',
            'ssb_receiver', 'shortwave_radio', 'short_band_transmitter',
            'weatherfax_navtex', 'satellite_communication',
        ]);
        $this->createSubTable('yacht_safety', [
            'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher', 'mob_system',
            'life_buoy', 'bilge_pump_manual', 'bilge_pump_electric',
            'radar_reflector', 'flares', 'life_jackets', 'watertight_door',
            'gas_bottle_locker', 'self_draining_cockpit',
        ]);
        $this->createSubTable('yacht_comfort', [
            'oven', 'microwave', 'fridge', 'freezer', 'heating', 'air_conditioning',
            'cooker', 'cooking_fuel', 'hot_air', 'stove', 'central_heating',
            'satellite_reception', 'television', 'cd_player', 'dvd_player',
            'water_tank', 'water_tank_material', 'water_tank_gauge', 'water_maker',
            'waste_water_tank', 'waste_water_tank_material', 'waste_water_tank_gauge',
            'waste_water_tank_drainpump', 'deck_suction', 'water_system', 'hot_water',
            'sea_water_pump', 'deck_wash_pump', 'deck_shower',
        ]);
        $this->createSubTable('yacht_deck_equipment', [
            'anchor', 'spray_hood', 'bimini', 'fenders',
            'anchor_connection', 'anchor_winch', 'stern_anchor', 'spud_pole',
            'cockpit_tent', 'outdoor_cushions', 'covers', 'sea_rails',
            'pushpit_pullpit', 'swimming_platform', 'swimming_ladder',
            'sail_lowering_system', 'crutch', 'dinghy', 'dinghy_brand',
            'outboard_engine', 'trailer', 'crane', 'davits', 'teak_deck',
            'cockpit_table', 'oars_paddles',
        ]);
        $this->createSubTable('yacht_rigging', [
            'sailplan_type', 'number_of_masts', 'spars_material', 'bowsprit',
            'standing_rig', 'sail_surface_area', 'stabilizer_sail', 'sail_amount',
            'sail_material', 'sail_manufacturer', 'genoa', 'main_sail',
            'furling_mainsail', 'tri_sail', 'storm_jib', 'mizzen', 'furling_mizzen',
            'jib', 'roller_furling_foresail', 'genoa_reefing_system', 'flying_jib',
            'spinnaker', 'halfwinder_bollejan', 'gennaker', 'winches',
            'electric_winches', 'manual_winches', 'hydraulic_winches',
            'self_tailing_winches',
        ]);

        Schema::create('yacht_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained()->onDelete('cascade');
            $table->string('url')->nullable();
            $table->string('category')->nullable();
            $table->string('part_name')->nullable();
            $table->integer('sort_order')->default(0);
            // Pipeline fields
            $table->string('original_name')->nullable();
            $table->string('original_temp_url')->nullable();
            $table->string('optimized_master_url')->nullable();
            $table->string('thumb_url')->nullable();
            $table->string('original_kept_url')->nullable();
            $table->boolean('keep_original')->default(false);
            $table->string('status')->default('uploaded');
            $table->string('enhancement_method')->default('none');
            $table->integer('quality_score')->nullable();
            $table->json('quality_flags')->nullable();
            $table->timestamps();
        });
        
        Schema::create('yacht_availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained()->onDelete('cascade');
            $table->integer('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yacht_availability_rules');
        Schema::dropIfExists('yacht_images');
        Schema::dropIfExists('yacht_rigging');
        Schema::dropIfExists('yacht_deck_equipment');
        Schema::dropIfExists('yacht_comfort');
        Schema::dropIfExists('yacht_safety');
        Schema::dropIfExists('yacht_navigation');
        Schema::dropIfExists('yacht_electrical');
        Schema::dropIfExists('yacht_engines');
        Schema::dropIfExists('yacht_accommodation');
        Schema::dropIfExists('yacht_construction');
        Schema::dropIfExists('yacht_dimensions');
        Schema::dropIfExists('yachts');
    }

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
            $table->timestamps();
        });
    }
};
