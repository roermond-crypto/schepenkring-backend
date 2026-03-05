<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Yacht;

class ImportYachtsFromJSON extends Command
{
    protected $signature = 'yachts:import-json';
    protected $description = 'Import yachts from local JSON file';

    public function handle()
    {
        $this->info("Reading local JSON file...");

        $jsonFile = __DIR__.'/boats.json';
        if (!file_exists($jsonFile)) {
            $this->error("JSON file not found: {$jsonFile}");
            return 1;
        }

        $jsonData = json_decode(file_get_contents($jsonFile), true);
        if (!$jsonData) {
            $this->error("Failed to decode JSON file.");
            return 1;
        }

        $count = 0;

        foreach ($jsonData as $data) {
            $yachtData = [
                'vessel_id' => $data['external_url'] ?? 'N/A',
                'name' => $data['boat_name'] ?? 'Unnamed Yacht',
                'status' => 'Draft',
                'allow_bidding' => 0,
                'price' => $data['price'] ?? 0,
                'user_id' => 1,
                'year' => $data['year'] ?? 'N/A',
                'length' => $data['length'] ?? 'N/A',
                'loa' => $data['loa'] ?? 'N/A',
                'lwl' => $data['lwl'] ?? 'N/A',
                'air_draft' => $data['air_draft'] ?? 'N/A',
                'designer' => $data['designer'] ?? 'N/A',
                'builder' => $data['builder'] ?? 'N/A',
                'where' => $data['where'] ?? 'N/A',
                'main_image' => $data['main_image'] ?? 'N/A',
                'make' => $data['make'] ?? 'N/A',
                'model' => $data['model'] ?? 'N/A',
                'beam' => $data['beam'] ?? 'N/A',
                'draft' => $data['draft'] ?? 'N/A',
                'engine_type' => $data['engine_type'] ?? 'N/A',
                'fuel_type' => $data['fuel_type'] ?? 'N/A',
                'fuel_capacity' => $data['fuel_capacity'] ?? 'N/A',
                'water_capacity' => $data['water_capacity'] ?? 'N/A',
                'cabins' => $data['cabins'] ?? 0,
                'heads' => $data['heads'] ?? 0,
                'description' => $data['description'] ?? 'N/A',
                'location' => $data['location'] ?? 'N/A',
                'brand_model' => $data['brand_model'] ?? 'N/A',
                'vat_status' => $data['vat_status'] ?? 'N/A',
                'reference_code' => $data['reference_code'] ?? 'N/A',
                'external_url' => $data['external_url'] ?? 'N/A',
                'print_url' => $data['print_url'] ?? 'N/A',
                'owners_comment' => $data['owners_comment'] ?? 'N/A',
                'reg_details' => $data['reg_details'] ?? 'N/A',
                'known_defects' => $data['known_defects'] ?? 'N/A',
                'last_serviced' => $data['last_serviced'] ?? 'N/A',
                'passenger_capacity' => $data['passenger_capacity'] ?? 0,
                'construction_material' => $data['construction_material'] ?? 'N/A',
                'dimensions' => $data['dimensions'] ?? 'N/A',
                'berths' => $data['berths'] ?? 'N/A',
                'hull_shape' => $data['hull_type'] ?? 'N/A',
                'hull_construction' => $data['hull_construction'] ?? 'N/A',
                'hull_color' => $data['hull_colour'] ?? 'N/A',
                'super_structure_colour' => $data['super_structure_colour'] ?? 'N/A',
                'super_structure_construction' => $data['super_structure_construction'] ?? 'N/A',
                'deck_color' => $data['deck_colour'] ?? 'N/A',
                'deck_construction' => $data['deck_construction'] ?? 'N/A',
                'cockpit_type' => $data['cockpit_type'] ?? 'N/A',
                'control_type' => $data['control_type'] ?? 'N/A',
                'flybridge' => $data['flybridge'] === 'true' ? 1 : 0,
                'oven' => $data['oven'] === 'true' ? 1 : 0,
                'microwave' => $data['microwave'] === 'true' ? 1 : 0,
                'fridge' => $data['fridge'] === 'true' ? 1 : 0,
                'freezer' => $data['freezer'] === 'true' ? 1 : 0,
                'air_conditioning' => $data['air_conditioning'] === 'true' ? 1 : 0,
                'stern_thruster' => $data['stern_thruster'] ?? 'N/A',
                'bow_thruster' => $data['bow_thruster'] ?? 'N/A',
                'horse_power' => $data['horse_power'] ?? 'N/A',
                'engine_manufacturer' => $data['engine_manufacturer'] ?? 'N/A',
                'engine_quantity' => $data['engine_quantity'] ?? null,
                'tankage' => $data['tankage'] ?? 'N/A',
                'gallons_per_hour' => $data['gallons_per_hour'] ?? 'N/A',
                'litres_per_hour' => $data['litres_per_hour'] ?? 'N/A',
                'engine_location' => $data['engine_location'] ?? 'N/A',
                'gearbox' => $data['gearbox'] ?? 'N/A',
                'cylinders' => $data['cylinders'] ?? 'N/A',
                'propeller_type' => $data['propeller_type'] ?? 'N/A',
                'starting_type' => $data['starting_type'] ?? 'N/A',
                'drive_type' => $data['drive_type'] ?? 'N/A',
                'cooling_system' => $data['cooling_system'] ?? 'N/A',
                'navigation_electronics' => json_encode([
                    'navigation_lights' => $data['navigation_lights'] ?? false,
                    'compass' => $data['compass'] ?? false,
                    'depth_instrument' => $data['depth_instrument'] ?? false,
                    'wind_instrument' => $data['wind_instrument'] ?? false,
                    'autopilot' => $data['autopilot'] ?? false,
                    'gps' => $data['gps'] ?? false,
                    'vhf' => $data['vhf'] ?? false,
                    'plotter' => $data['plotter'] ?? false,
                    'speed_instrument' => $data['speed_instrument'] ?? false,
                    'radar' => $data['radar'] ?? false,
                ]),
                'exterior_equipment' => json_encode([
                    'toilet' => $data['toilet'] ?? 0,
                    'shower' => $data['shower'] ?? 0,
                    'bath' => $data['bath'] ?? 0,
                    'life_raft' => $data['life_raft'] ?? false,
                    'epirb' => $data['epirb'] ?? false,
                    'bilge_pump' => $data['bilge_pump'] ?? false,
                    'fire_extinguisher' => $data['fire_extinguisher'] ?? false,
                    'mob_system' => $data['mob_system'] ?? false,
                    'genoa' => $data['genoa'] ?? false,
                    'spinnaker' => $data['spinnaker'] ?? false,
                    'tri_sail' => $data['tri_sail'] ?? false,
                    'storm_jib' => $data['storm_jib'] ?? false,
                    'main_sail' => $data['main_sail'] ?? false,
                    'winches' => $data['winches'] ?? 'N/A',
                    'battery' => $data['battery'] ?? false,
                    'battery_charger' => $data['battery_charger'] ?? false,
                    'generator' => $data['generator'] ?? false,
                    'inverter' => $data['inverter'] ?? false,
                    'television' => $data['television'] ?? false,
                    'cd_player' => $data['cd_player'] ?? false,
                    'dvd_player' => $data['dvd_player'] ?? false,
                    'anchor' => $data['Anchor'] ?? false,
                    'spray_hood' => $data['spray_hood'] ?? false,
                    'bimini' => $data['Bimini'] ?? false,
                    'fenders' => $data['fenders'] ?? 'N/A',
                    'hours_counter' => $data['hours'] ?? 'N/A',
                    'cruising_speed' => $data['cruising_speed'] ?? 'N/A',
                    'max_draft' => $data['max_speed'] ?? 'N/A',
                    'min_draft' => $data['min_draft'] ?? 'N/A',
                    'fuel' => $data['fuel'] ?? 'N/A',
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ];

            Yacht::updateOrCreate(
                ['vessel_id' => $yachtData['vessel_id']],
                $yachtData
            );

            $count++;
            $this->info("Inserted/Updated yacht: {$yachtData['name']}");
        }

        $this->info("Imported/Updated {$count} yachts successfully.");
    }
}
