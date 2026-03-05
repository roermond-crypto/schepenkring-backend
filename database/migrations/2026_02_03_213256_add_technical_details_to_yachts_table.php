<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('yachts', function (Blueprint $table) {
        // General Specs
        if (!Schema::hasColumn('yachts', 'brand_model')) {
            $table->string('brand_model')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'vat_status')) {
            $table->string('vat_status')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'reference_code')) {
            $table->string('reference_code')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'construction_material')) {
            $table->string('construction_material')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'dimensions')) {
            $table->string('dimensions')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'berths')) {
            $table->string('berths')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'hull_shape')) {
            $table->string('hull_shape')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'hull_color')) {
            $table->string('hull_color')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'deck_color')) {
            $table->string('deck_color')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'clearance')) {
            $table->string('clearance')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'displacement')) {
            $table->string('displacement')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'steering')) {
            $table->string('steering')->nullable();
        }

        // Engine & Electricity
        if (!Schema::hasColumn('yachts', 'engine_brand')) {
            $table->string('engine_brand')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'engine_model')) {
            $table->string('engine_model')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'engine_power')) {
            $table->string('engine_power')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'engine_type')) {
            $table->string('engine_type')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'fuel_type')) {
            $table->string('fuel_type')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'engine_hours')) {
            $table->string('engine_hours')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'max_speed')) {
            $table->string('max_speed')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'fuel_consumption')) {
            $table->string('fuel_consumption')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'voltage')) {
            $table->string('voltage')->nullable();
        }

        // Accommodation
        if (!Schema::hasColumn('yachts', 'cabins')) {
            $table->string('cabins')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'interior_type')) {
            $table->string('interior_type')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'water_tank')) {
            $table->string('water_tank')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'water_system')) {
            $table->string('water_system')->nullable();
        }

        // Equipment & Safety
        if (!Schema::hasColumn('yachts', 'navigation_electronics')) {
            $table->text('navigation_electronics')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'exterior_equipment')) {
            $table->text('exterior_equipment')->nullable();
        }
        if (!Schema::hasColumn('yachts', 'trailer_included')) {
            $table->boolean('trailer_included')->default(false);
        }
        if (!Schema::hasColumn('yachts', 'safety_equipment')) {
            $table->string('safety_equipment')->nullable();
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            //
        });
    }
};
