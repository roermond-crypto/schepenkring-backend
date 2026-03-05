<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Yacht; // Ensure you have a Yacht model
use Illuminate\Support\Facades\File;

class YachtSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Path to your json file
        $json = File::get(database_path('data/yachts.json'));
        $data = json_decode($json, true);

        // Handle both a single object or an array of objects
        $yachts = isset($data[0]) ? $data : [$data];

        foreach ($yachts as $item) {
            Yacht::create([
                'boat_name'          => $item['boat_name'],
                'external_url'       => $item['external_url'],
                'print_url'          => $item['print_url'],
                'owners_comment'     => $item['owners_comment'],
                'reg_details'        => $item['reg_details'],
                'known_defects'      => $item['known_defects'],
                'last_serviced'      => $item['last_serviced'],
                'beam'               => $item['beam'],
                'draft'              => $item['draft'],
                'loa'                => $item['loa'],
                'lwl'                => $item['lwl'],
                'air_draft'          => $item['air_draft'],
                'passenger_capacity' => $item['passenger_capacity'],
                'designer'           => $item['designer'],
                'builder'            => $item['builder'],
                'where'              => $item['where'],
                'year'               => $item['year'],
                'hull_colour'        => $item['hull_colour'],
                'hull_construction'  => $item['hull_construction'],
                'super_structure_construction' => $item['super_structure_construction'],
                'deck_colour'        => $item['deck_colour'],
                'tankage'            => $item['tankage'],
                'litres_per_hour'    => $item['litres_per_hour'],
                'cylinders'          => $item['cylinders'],
                'starting_type'      => $item['starting_type'],
                'cooling_system'     => $item['cooling_system'],
                'fenders'            => $item['fenders'],
                
                // Boolean Conversions (Handling "true"/"false" strings)
                'flybridge'          => filter_var($item['flybridge'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'oven'               => filter_var($item['oven'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'microwave'          => filter_var($item['microwave'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'fridge'             => filter_var($item['fridge'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'freezer'            => filter_var($item['freezer'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'air_conditioning'   => filter_var($item['air_conditioning'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'navigation_lights'  => filter_var($item['navigation_lights'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'compass'            => filter_var($item['compass'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'depth_instrument'   => filter_var($item['depth_instrument'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'wind_instrument'    => filter_var($item['wind_instrument'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'autopilot'          => filter_var($item['autopilot'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'gps'                => filter_var($item['gps'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'vhf'                => filter_var($item['vhf'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'plotter'            => filter_var($item['plotter'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'speed_instrument'   => filter_var($item['speed_instrument'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'radar'              => filter_var($item['radar'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'life_raft'          => filter_var($item['life_raft'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'epirb'              => filter_var($item['epirb'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'bilge_pump'         => filter_var($item['bilge_pump'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'fire_extinguisher'  => filter_var($item['fire_extinguisher'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'mob_system'         => filter_var($item['mob_system'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'spinnaker'          => filter_var($item['spinnaker'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'battery'            => filter_var($item['battery'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'battery_charger'    => filter_var($item['battery_charger'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'generator'          => filter_var($item['generator'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'inverter'           => filter_var($item['inverter'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'television'         => filter_var($item['television'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'cd_player'          => filter_var($item['cd_player'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'dvd_player'         => filter_var($item['dvd_player'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'anchor'             => filter_var($item['Anchor'] ?? false, FILTER_VALIDATE_BOOLEAN), // Note the capital 'A' in JSON
                'spray_hood'         => filter_var($item['spray_hood'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'bimini'             => filter_var($item['Bimini'] ?? false, FILTER_VALIDATE_BOOLEAN), // Note the capital 'B' in JSON
            ]);
        }
    }
}