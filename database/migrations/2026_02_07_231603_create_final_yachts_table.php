<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign keys first from dependent tables
        Schema::table('yacht_images', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });
        
        Schema::table('bids', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });
        
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });
        
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });
        
        Schema::table('yacht_availability_rules', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });

        // Drop the old yachts table
        Schema::dropIfExists('yachts');

        // Create new yachts table with proper structure
        Schema::create('yachts', function (Blueprint $table) {
            $table->id();
            
            // Core identification
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('vessel_id')->unique()->nullable();
            $table->string('boat_name');
            $table->decimal('price', 15, 2)->nullable();
            $table->enum('status', ['For Sale', 'For Bid', 'Sold', 'Draft'])->default('Draft');
            $table->boolean('allow_bidding')->default(false);
            $table->string('main_image')->nullable();
            
            // URLs and references
            $table->string('external_url')->nullable();
            $table->string('print_url')->nullable();
            $table->text('owners_comment')->nullable();
            $table->text('reg_details')->nullable();
            $table->text('known_defects')->nullable();
            $table->string('last_serviced')->nullable();
            
            // Dimensions
            $table->string('beam')->nullable();
            $table->string('draft')->nullable();
            $table->string('loa')->nullable();
            $table->string('lwl')->nullable();
            $table->string('air_draft')->nullable();
            $table->integer('passenger_capacity')->nullable();
            
            // Construction
            $table->string('designer')->nullable();
            $table->string('builder')->nullable();
            $table->string('where')->nullable();
            $table->integer('year')->nullable();
            $table->string('hull_colour')->nullable();
            $table->string('hull_construction')->nullable();
            $table->string('hull_number')->nullable();
            $table->string('hull_type')->nullable();
            $table->string('super_structure_colour')->nullable();
            $table->string('super_structure_construction')->nullable();
            $table->string('deck_colour')->nullable();
            $table->string('deck_construction')->nullable();
            
            // Configuration
            $table->string('cockpit_type')->nullable();
            $table->string('control_type')->nullable();
            $table->boolean('flybridge')->default(false);
            $table->text('ballast')->nullable();
            $table->string('displacement')->nullable();
            
            // Accommodation
            $table->text('cabins')->nullable();
            $table->text('berths')->nullable();
            $table->text('toilet')->nullable();
            $table->text('shower')->nullable();
            $table->text('bath')->nullable();
            
            // Kitchen equipment
            $table->boolean('oven')->default(false);
            $table->boolean('microwave')->default(false);
            $table->boolean('fridge')->default(false);
            $table->boolean('freezer')->default(false);
            $table->string('heating')->nullable();
            $table->boolean('air_conditioning')->default(false);
            
            // Engine and propulsion
            $table->string('stern_thruster')->nullable();
            $table->string('bow_thruster')->nullable();
            $table->string('fuel')->nullable();
            $table->string('hours')->nullable();
            $table->string('cruising_speed')->nullable();
            $table->string('max_speed')->nullable();
            $table->string('horse_power')->nullable();
            $table->string('engine_manufacturer')->nullable();
            $table->text('engine_quantity')->nullable();
            $table->text('tankage')->nullable();
            $table->string('gallons_per_hour')->nullable();
            $table->text('litres_per_hour')->nullable();
            $table->text('engine_location')->nullable();
            $table->text('gearbox')->nullable();
            $table->text('cylinders')->nullable();
            $table->text('propeller_type')->nullable();
            $table->string('starting_type')->nullable();
            $table->string('drive_type')->nullable();
            $table->text('cooling_system')->nullable();
            
            // Navigation equipment
            $table->boolean('navigation_lights')->default(false);
            $table->boolean('compass')->default(false);
            $table->boolean('depth_instrument')->default(false);
            $table->boolean('wind_instrument')->default(false);
            $table->boolean('autopilot')->default(false);
            $table->boolean('gps')->default(false);
            $table->boolean('vhf')->default(false);
            $table->boolean('plotter')->default(false);
            $table->boolean('speed_instrument')->default(false);
            $table->boolean('radar')->default(false);
            
            // Safety equipment
            $table->boolean('life_raft')->default(false);
            $table->boolean('epirb')->default(false);
            $table->boolean('bilge_pump')->default(false);
            $table->boolean('fire_extinguisher')->default(false);
            $table->boolean('mob_system')->default(false);
            
            // Sailing equipment
            $table->text('genoa')->nullable();
            $table->boolean('spinnaker')->default(false);
            $table->text('tri_sail')->nullable();
            $table->text('storm_jib')->nullable();
            $table->text('main_sail')->nullable();
            $table->text('winches')->nullable();
            
            // Electrical
            $table->boolean('battery')->default(false);
            $table->boolean('battery_charger')->default(false);
            $table->boolean('generator')->default(false);
            $table->boolean('inverter')->default(false);
            
            // Entertainment
            $table->boolean('television')->default(false);
            $table->boolean('cd_player')->default(false);
            $table->boolean('dvd_player')->default(false);
            
            // Deck equipment
            $table->boolean('anchor')->default(false);
            $table->boolean('spray_hood')->default(false);
            $table->boolean('bimini')->default(false);
            $table->string('fenders')->nullable();
            
            $table->timestamps();
        });

        // Re-add foreign keys
        Schema::table('yacht_images', function (Blueprint $table) {
            $table->foreign('yacht_id')->references('id')->on('yachts')->onDelete('cascade');
        });
        
        Schema::table('bids', function (Blueprint $table) {
            $table->foreign('yacht_id')->references('id')->on('yachts')->onDelete('cascade');
        });
        
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('yacht_id')->references('id')->on('yachts')->onDelete('cascade');
        });
        
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('yacht_id')->references('id')->on('yachts')->onDelete('cascade');
        });
        
        Schema::table('yacht_availability_rules', function (Blueprint $table) {
            $table->foreign('yacht_id')->references('id')->on('yachts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Drop foreign keys
        Schema::table('yacht_images', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });
        
        Schema::table('bids', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });
        
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });
        
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });
        
        Schema::table('yacht_availability_rules', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
        });

        Schema::dropIfExists('yachts');
    }
};