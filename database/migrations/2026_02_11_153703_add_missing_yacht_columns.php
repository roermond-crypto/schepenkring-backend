<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingYachtColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('yachts', function (Blueprint $table) {
            // ----- Financial Fields -----
            if (!Schema::hasColumn('yachts', 'min_bid_amount')) {
                $table->decimal('min_bid_amount', 15, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('yachts', 'passenger_capacity')) {
                $table->integer('passenger_capacity')->nullable()->after('where');
            }

            // ----- Hull & Dimensions -----
            if (!Schema::hasColumn('yachts', 'lwl')) {
                $table->string('lwl', 50)->nullable()->after('loa');
            }
            if (!Schema::hasColumn('yachts', 'air_draft')) {
                $table->string('air_draft', 50)->nullable()->after('draft');
            }
            if (!Schema::hasColumn('yachts', 'displacement')) {
                $table->string('displacement', 50)->nullable()->after('air_draft');
            }
            if (!Schema::hasColumn('yachts', 'ballast')) {
                $table->string('ballast', 50)->nullable()->after('displacement');
            }

            // ----- Construction & Appearance -----
            if (!Schema::hasColumn('yachts', 'designer')) {
                $table->string('designer', 100)->nullable()->after('year');
            }
            if (!Schema::hasColumn('yachts', 'builder')) {
                $table->string('builder', 100)->nullable()->after('designer');
            }
            if (!Schema::hasColumn('yachts', 'where')) {
                $table->string('where', 100)->nullable()->after('builder');
            }
            if (!Schema::hasColumn('yachts', 'hull_colour')) {
                $table->string('hull_colour', 50)->nullable()->after('hull_type');
            }
            if (!Schema::hasColumn('yachts', 'hull_construction')) {
                $table->string('hull_construction', 100)->nullable()->after('hull_colour');
            }
            if (!Schema::hasColumn('yachts', 'hull_number')) {
                $table->string('hull_number', 50)->nullable()->after('hull_construction');
            }
            if (!Schema::hasColumn('yachts', 'super_structure_colour')) {
                $table->string('super_structure_colour', 50)->nullable()->after('hull_number');
            }
            if (!Schema::hasColumn('yachts', 'super_structure_construction')) {
                $table->string('super_structure_construction', 100)->nullable()->after('super_structure_colour');
            }
            if (!Schema::hasColumn('yachts', 'deck_colour')) {
                $table->string('deck_colour', 50)->nullable()->after('super_structure_construction');
            }
            if (!Schema::hasColumn('yachts', 'deck_construction')) {
                $table->string('deck_construction', 100)->nullable()->after('deck_colour');
            }
            if (!Schema::hasColumn('yachts', 'cockpit_type')) {
                $table->string('cockpit_type', 50)->nullable()->after('heating');
            }
            if (!Schema::hasColumn('yachts', 'control_type')) {
                $table->string('control_type', 50)->nullable()->after('cockpit_type');
            }

            // ----- Engine & Performance -----
            if (!Schema::hasColumn('yachts', 'engine_manufacturer')) {
                $table->string('engine_manufacturer', 100)->nullable()->after('builder');
            }
            if (!Schema::hasColumn('yachts', 'horse_power')) {
                $table->string('horse_power', 50)->nullable()->after('engine_manufacturer');
            }
            if (!Schema::hasColumn('yachts', 'hours')) {
                $table->string('hours', 50)->nullable()->after('horse_power');
            }
            if (!Schema::hasColumn('yachts', 'fuel')) {
                $table->string('fuel', 50)->nullable()->after('hours');
            }
            if (!Schema::hasColumn('yachts', 'max_speed')) {
                $table->string('max_speed', 50)->nullable()->after('fuel');
            }
            if (!Schema::hasColumn('yachts', 'cruising_speed')) {
                $table->string('cruising_speed', 50)->nullable()->after('max_speed');
            }
            if (!Schema::hasColumn('yachts', 'gallons_per_hour')) {
                $table->string('gallons_per_hour', 50)->nullable()->after('cruising_speed');
            }
            if (!Schema::hasColumn('yachts', 'tankage')) {
                $table->string('tankage', 50)->nullable()->after('gallons_per_hour');
            }
            if (!Schema::hasColumn('yachts', 'starting_type')) {
                $table->string('starting_type', 50)->nullable()->after('tankage');
            }
            if (!Schema::hasColumn('yachts', 'drive_type')) {
                $table->string('drive_type', 50)->nullable()->after('starting_type');
            }

            // ----- Accommodation -----
            if (!Schema::hasColumn('yachts', 'cabins')) {
                $table->integer('cabins')->nullable()->after('passenger_capacity');
            }
            if (!Schema::hasColumn('yachts', 'berths')) {
                $table->integer('berths')->nullable()->after('cabins');
            }
            if (!Schema::hasColumn('yachts', 'toilet')) {
                $table->integer('toilet')->nullable()->after('berths');
            }
            if (!Schema::hasColumn('yachts', 'shower')) {
                $table->integer('shower')->nullable()->after('toilet');
            }
            if (!Schema::hasColumn('yachts', 'bath')) {
                $table->integer('bath')->nullable()->after('shower');
            }
            if (!Schema::hasColumn('yachts', 'heating')) {
                $table->string('heating', 100)->nullable()->after('bath');
            }

            // ----- Thruster Fields -----
            if (!Schema::hasColumn('yachts', 'stern_thruster')) {
                $table->boolean('stern_thruster')->default(false)->after('bimini');
            }
            if (!Schema::hasColumn('yachts', 'bow_thruster')) {
                $table->boolean('bow_thruster')->default(false)->after('stern_thruster');
            }

            // ----- Boolean Equipment Fields -----
            $booleanFields = [
                'allow_bidding', 'flybridge', 'oven', 'microwave', 'fridge', 'freezer',
                'air_conditioning', 'navigation_lights', 'compass', 'depth_instrument',
                'wind_instrument', 'autopilot', 'gps', 'vhf', 'plotter', 'speed_instrument',
                'radar', 'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher',
                'mob_system', 'spinnaker', 'battery', 'battery_charger', 'generator',
                'inverter', 'television', 'cd_player', 'dvd_player', 'anchor',
                'spray_hood', 'bimini'
            ];

            foreach ($booleanFields as $field) {
                if (!Schema::hasColumn('yachts', $field)) {
                    $table->boolean($field)->default(false)->after('status');
                }
            }

            // ----- JSON / Text Fields -----
            if (!Schema::hasColumn('yachts', 'display_specs')) {
                $table->json('display_specs')->nullable()->after('drive_type');
            }
            if (!Schema::hasColumn('yachts', 'external_url')) {
                $table->string('external_url', 255)->nullable()->after('display_specs');
            }
            if (!Schema::hasColumn('yachts', 'print_url')) {
                $table->string('print_url', 255)->nullable()->after('external_url');
            }
            if (!Schema::hasColumn('yachts', 'owners_comment')) {
                $table->text('owners_comment')->nullable()->after('print_url');
            }
            if (!Schema::hasColumn('yachts', 'reg_details')) {
                $table->string('reg_details', 255)->nullable()->after('owners_comment');
            }
            if (!Schema::hasColumn('yachts', 'known_defects')) {
                $table->text('known_defects')->nullable()->after('reg_details');
            }
            if (!Schema::hasColumn('yachts', 'last_serviced')) {
                $table->date('last_serviced')->nullable()->after('known_defects');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('yachts', function (Blueprint $table) {
            // List all columns that were added – optionally drop them in reverse.
            // Since this is a production safety measure, we leave this empty.
            // If you need to rollback, add dropColumn statements here.
        });
    }
}