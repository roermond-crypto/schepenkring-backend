<?php

namespace Database\Seeders;

use App\Models\BoatField;
use App\Models\BoatFieldPriority;
use Illuminate\Database\Seeder;

class BoatFieldSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->specFieldDefinitions() as $definition) {
            $field = BoatField::query()->updateOrCreate(
                ['internal_key' => $definition['internal_key']],
                [
                    'labels_json' => $definition['labels_json'],
                    'options_json' => $definition['options_json'] ?: null,
                    'field_type' => $definition['field_type'],
                    'block_key' => $definition['block_key'],
                    'step_key' => $definition['step_key'],
                    'sort_order' => $definition['sort_order'],
                    'storage_relation' => $definition['storage_relation'],
                    'storage_column' => $definition['storage_column'],
                    'ai_relevance' => $definition['ai_relevance'],
                    'is_active' => true,
                ],
            );

            $this->syncPriorities($field, $definition['priorities']);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function specFieldDefinitions(): array
    {
        return array_merge(
            $this->buildBlock('hull_dimensions', [
                $this->field('beam', 'Beam (Width)', 'dimensions', 'beam', priorities: $this->priorities('primary')),
                $this->field('draft', 'Draft (Depth)', 'dimensions', 'draft', priorities: $this->priorities('primary')),
                $this->field('air_draft', 'Air Draft (Clearance)', 'dimensions', 'air_draft', priorities: $this->priorities('primary')),
                $this->field('displacement', 'Displacement', 'dimensions', 'displacement', priorities: $this->priorities('primary')),
                $this->field('ballast', 'Ballast', 'dimensions', 'ballast', priorities: $this->priorities('secondary', ['sailboat' => 'primary', 'catamaran' => 'secondary'])),
                $this->field('hull_type', 'Hull Type', 'construction', 'hull_type', priorities: $this->priorities('primary')),
                $this->field('hull_construction', 'Hull Construction', 'construction', 'hull_construction', priorities: $this->priorities('primary')),
                $this->field('hull_colour', 'Hull Colour', 'construction', 'hull_colour', priorities: $this->priorities('secondary')),
                $this->field('hull_number', 'Hull Number', 'construction', 'hull_number', priorities: $this->priorities('secondary')),
                $this->field('designer', 'Designer', 'construction', 'designer', priorities: $this->priorities('secondary', ['sailboat' => 'primary'])),
                $this->field('builder', 'Builder', 'construction', 'builder', priorities: $this->priorities('primary')),
                $this->field('deck_colour', 'Deck Colour', 'construction', 'deck_colour', priorities: $this->priorities('secondary')),
                $this->field('deck_construction', 'Deck Construction', 'construction', 'deck_construction', priorities: $this->priorities('secondary')),
                $this->field('super_structure_colour', 'Superstructure Colour', 'construction', 'super_structure_colour', priorities: $this->priorities('secondary')),
                $this->field('super_structure_construction', 'Superstructure Construction', 'construction', 'super_structure_construction', priorities: $this->priorities('secondary')),
                $this->field('cockpit_type', 'Cockpit Type', 'construction', 'cockpit_type', priorities: $this->priorities('secondary')),
                $this->field('control_type', 'Control Type', 'construction', 'control_type', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
                $this->field('flybridge', 'Flybridge', 'construction', 'flybridge', fieldType: 'tri_state', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
            ]),
            $this->buildBlock('engine', [
                $this->field('engine_manufacturer', 'Engine Manufacturer', 'engine', 'engine_manufacturer', priorities: $this->priorities('primary')),
                $this->field('engine_model', 'Engine Model', 'engine', 'engine_model', priorities: $this->priorities('primary')),
                $this->field(
                    'engine_type',
                    'Engine Type',
                    'engine',
                    'engine_type',
                    fieldType: 'select',
                    priorities: $this->priorities('primary'),
                    options: [
                        $this->option('inboard', 'Inboard'),
                        $this->option('outboard', 'Outboard'),
                        $this->option('saildrive', 'Saildrive'),
                        $this->option('sterndrive', 'Sterndrive'),
                    ],
                ),
                $this->field('horse_power', 'Horse Power', 'engine', 'horse_power', priorities: $this->priorities('primary')),
                $this->field('hours', 'Engine Hours', 'engine', 'hours', priorities: $this->priorities('primary')),
                $this->field('fuel', 'Fuel Type', 'engine', 'fuel', priorities: $this->priorities('primary')),
                $this->field('engine_quantity', 'Engine Quantity', 'engine', 'engine_quantity', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
                $this->field('engine_year', 'Engine Year', 'engine', 'engine_year', fieldType: 'number', priorities: $this->priorities('secondary')),
                $this->field('max_speed', 'Max Speed', 'engine', 'max_speed', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
                $this->field('cruising_speed', 'Cruising Speed', 'engine', 'cruising_speed', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
                $this->field('drive_type', 'Drive Type', 'engine', 'drive_type', priorities: $this->priorities('secondary')),
                $this->field('propulsion', 'Propulsion', 'engine', 'propulsion', priorities: $this->priorities('secondary', ['sailboat' => 'primary'])),
                $this->field('gallons_per_hour', 'Gallons per Hour', 'engine', 'gallons_per_hour', priorities: $this->priorities('secondary')),
                $this->field('tankage', 'Tankage', 'engine', 'tankage', priorities: $this->priorities('secondary')),
            ]),
            $this->buildBlock('interior', [
                $this->field('cabins', 'Cabins', 'accommodation', 'cabins', fieldType: 'number', priorities: $this->priorities('primary', ['sailboat' => 'primary', 'motorboat' => 'secondary'])),
                $this->field('berths', 'Berths', 'accommodation', 'berths', priorities: $this->priorities('primary', ['motorboat' => 'primary'])),
                $this->field('toilet', 'Toilet', 'accommodation', 'toilet', fieldType: 'tri_state', priorities: $this->priorities('primary')),
                $this->field('shower', 'Shower', 'accommodation', 'shower', fieldType: 'tri_state', priorities: $this->priorities('primary')),
                $this->field('heating', 'Heating', 'comfort', 'heating', fieldType: 'tri_state', priorities: $this->priorities('primary')),
                $this->field('bath', 'Bath', 'accommodation', 'bath', fieldType: 'tri_state', priorities: $this->priorities('secondary')),
                $this->field('interior_type', 'Interior Type', 'accommodation', 'interior_type', priorities: $this->priorities('secondary')),
                $this->field('saloon', 'Saloon', 'accommodation', 'saloon', priorities: $this->priorities('secondary')),
                $this->field('headroom', 'Headroom', 'accommodation', 'headroom', priorities: $this->priorities('secondary')),
                $this->field('separate_dining_area', 'Separate Dining Area', 'accommodation', 'separate_dining_area', priorities: $this->priorities('secondary')),
                $this->field('engine_room', 'Engine Room', 'accommodation', 'engine_room', priorities: $this->priorities('secondary')),
                $this->field('spaces_inside', 'Spaces Inside', 'accommodation', 'spaces_inside', priorities: $this->priorities('secondary')),
                $this->field('upholstery_color', 'Upholstery Color', 'accommodation', 'upholstery_color', priorities: $this->priorities('secondary')),
                $this->field('matrasses', 'Matrasses', 'accommodation', 'matrasses', priorities: $this->priorities('secondary')),
                $this->field('cushions', 'Cushions', 'accommodation', 'cushions', priorities: $this->priorities('secondary')),
                $this->field('curtains', 'Curtains', 'accommodation', 'curtains', priorities: $this->priorities('secondary')),
                $this->field('berths_fixed', 'Berths (Fixed)', 'accommodation', 'berths_fixed', fieldType: 'number', priorities: $this->priorities('secondary')),
                $this->field('berths_extra', 'Berths (Extra)', 'accommodation', 'berths_extra', fieldType: 'number', priorities: $this->priorities('secondary')),
                $this->field('berths_crew', 'Berths (Crew)', 'accommodation', 'berths_crew', fieldType: 'number', priorities: $this->priorities('secondary')),
                $this->field('air_conditioning', 'Air Conditioning', 'comfort', 'air_conditioning', fieldType: 'tri_state', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
            ]),
            $this->buildBlock('navigation', [
                $this->field('compass', 'Compass', 'navigation', 'compass', priorities: $this->priorities('primary')),
                $this->field('depth_instrument', 'Depth Instrument', 'navigation', 'depth_instrument', priorities: $this->priorities('primary')),
                $this->field('wind_instrument', 'Wind Instrument', 'navigation', 'wind_instrument', priorities: $this->priorities('secondary', ['sailboat' => 'primary', 'catamaran' => 'primary'])),
                $this->field('navigation_lights', 'Navigation Lights', 'navigation', 'navigation_lights', priorities: $this->priorities('secondary')),
                $this->field('autopilot', 'Autopilot', 'navigation', 'autopilot', priorities: $this->priorities('primary')),
                $this->field('gps', 'GPS', 'navigation', 'gps', priorities: $this->priorities('primary')),
                $this->field('vhf', 'VHF / Marifoon', 'navigation', 'vhf', priorities: $this->priorities('primary')),
                $this->field('plotter', 'Chart Plotter', 'navigation', 'plotter', priorities: $this->priorities('primary')),
                $this->field('speed_instrument', 'Log / Speed', 'navigation', 'speed_instrument', priorities: $this->priorities('secondary')),
                $this->field('radar', 'Radar', 'navigation', 'radar', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
                $this->field('fishfinder', 'Fishfinder', 'navigation', 'fishfinder', priorities: $this->priorities('secondary')),
                $this->field('ais', 'AIS', 'navigation', 'ais', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
                $this->field('log_speed', 'Log / Speed', 'navigation', 'log_speed', priorities: $this->priorities('secondary')),
                $this->field('rudder_position_indicator', 'Rudder Position Indicator', 'navigation', 'rudder_position_indicator', priorities: $this->priorities('secondary')),
                $this->field('turn_indicator', 'Turn Indicator', 'navigation', 'turn_indicator', priorities: $this->priorities('secondary')),
                $this->field('ssb_receiver', 'SSB Receiver', 'navigation', 'ssb_receiver', priorities: $this->priorities('secondary')),
                $this->field('shortwave_radio', 'Shortwave Radio', 'navigation', 'shortwave_radio', priorities: $this->priorities('secondary')),
                $this->field('short_band_transmitter', 'Short Band Transmitter', 'navigation', 'short_band_transmitter', priorities: $this->priorities('secondary')),
                $this->field('satellite_communication', 'Satellite Communication', 'navigation', 'satellite_communication', priorities: $this->priorities('secondary')),
                $this->field('weatherfax_navtex', 'Weatherfax / Navtex', 'navigation', 'weatherfax_navtex', priorities: $this->priorities('secondary')),
                $this->field('charts_guides', 'Charts / Guides', 'navigation', 'charts_guides', priorities: $this->priorities('secondary')),
            ]),
            $this->buildBlock('safety', [
                $this->field('life_raft', 'Life Raft', 'safety', 'life_raft', priorities: $this->priorities('primary')),
                $this->field('epirb', 'EPIRB', 'safety', 'epirb', priorities: $this->priorities('primary')),
                $this->field('bilge_pump', 'Bilge Pump', 'safety', 'bilge_pump', priorities: $this->priorities('primary')),
                $this->field('fire_extinguisher', 'Fire Extinguisher', 'safety', 'fire_extinguisher', priorities: $this->priorities('primary')),
                $this->field('life_jackets', 'Life Jackets', 'safety', 'life_jackets', priorities: $this->priorities('primary')),
                $this->field('bilge_pump_manual', 'Bilge Pump (Manual)', 'safety', 'bilge_pump_manual', priorities: $this->priorities('secondary')),
                $this->field('bilge_pump_electric', 'Bilge Pump (Electric)', 'safety', 'bilge_pump_electric', priorities: $this->priorities('secondary')),
                $this->field('mob_system', 'MOB System', 'safety', 'mob_system', priorities: $this->priorities('secondary')),
                $this->field('radar_reflector', 'Radar Reflector', 'safety', 'radar_reflector', priorities: $this->priorities('secondary')),
                $this->field('flares', 'Flares', 'safety', 'flares', priorities: $this->priorities('secondary')),
                $this->field('life_buoy', 'Life Buoy', 'safety', 'life_buoy', priorities: $this->priorities('secondary')),
                $this->field('watertight_door', 'Watertight Door', 'safety', 'watertight_door', priorities: $this->priorities('secondary')),
                $this->field('gas_bottle_locker', 'Gas Bottle Locker', 'safety', 'gas_bottle_locker', priorities: $this->priorities('secondary')),
                $this->field('self_draining_cockpit', 'Self Draining Cockpit', 'safety', 'self_draining_cockpit', priorities: $this->priorities('secondary')),
            ]),
            $this->buildBlock('electrical', [
                $this->field('battery', 'Batteries', 'electrical', 'battery', priorities: $this->priorities('primary')),
                $this->field('battery_charger', 'Battery Charger', 'electrical', 'battery_charger', priorities: $this->priorities('primary')),
                $this->field('generator', 'Generator', 'electrical', 'generator', priorities: $this->priorities('primary', ['motorboat' => 'primary', 'sailboat' => 'secondary'])),
                $this->field('inverter', 'Inverter', 'electrical', 'inverter', priorities: $this->priorities('primary')),
                $this->field('shorepower', 'Shorepower', 'electrical', 'shorepower', priorities: $this->priorities('primary')),
                $this->field('solar_panel', 'Solar Panel', 'electrical', 'solar_panel', priorities: $this->priorities('secondary', ['sailboat' => 'primary'])),
                $this->field('wind_generator', 'Wind Generator', 'electrical', 'wind_generator', priorities: $this->priorities('secondary', ['sailboat' => 'primary'])),
                $this->field('voltage', 'Voltage', 'electrical', 'voltage', priorities: $this->priorities('secondary')),
                $this->field('dynamo', 'Dynamo', 'electrical', 'dynamo', priorities: $this->priorities('secondary')),
                $this->field('accumonitor', 'Accumonitor', 'electrical', 'accumonitor', priorities: $this->priorities('secondary')),
                $this->field('voltmeter', 'Voltmeter', 'electrical', 'voltmeter', priorities: $this->priorities('secondary')),
                $this->field('shore_power_cable', 'Shore Power Cable', 'electrical', 'shore_power_cable', priorities: $this->priorities('secondary')),
                $this->field('consumption_monitor', 'Consumption Monitor', 'electrical', 'consumption_monitor', priorities: $this->priorities('secondary')),
                $this->field('control_panel', 'Control Panel', 'electrical', 'control_panel', priorities: $this->priorities('secondary')),
                $this->field('fuel_tank_gauge', 'Fuel Tank Gauge', 'electrical', 'fuel_tank_gauge', priorities: $this->priorities('secondary')),
                $this->field('tachometer', 'Tachometer', 'electrical', 'tachometer', priorities: $this->priorities('secondary')),
                $this->field('oil_pressure_gauge', 'Oil Pressure Gauge', 'electrical', 'oil_pressure_gauge', priorities: $this->priorities('secondary')),
                $this->field('temperature_gauge', 'Temperature Gauge', 'electrical', 'temperature_gauge', priorities: $this->priorities('secondary')),
            ]),
            $this->buildBlock('comfort', [
                $this->field('cooker', 'Cooker', 'comfort', 'cooker', priorities: $this->priorities('primary')),
                $this->field('cooking_fuel', 'Cooking Fuel', 'comfort', 'cooking_fuel', priorities: $this->priorities('secondary')),
                $this->field('oven', 'Oven', 'comfort', 'oven', fieldType: 'tri_state', priorities: $this->priorities('secondary')),
                $this->field('microwave', 'Microwave', 'comfort', 'microwave', fieldType: 'tri_state', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
                $this->field('fridge', 'Fridge', 'comfort', 'fridge', fieldType: 'tri_state', priorities: $this->priorities('primary')),
                $this->field('freezer', 'Freezer', 'comfort', 'freezer', fieldType: 'tri_state', priorities: $this->priorities('secondary')),
                $this->field('television', 'Television', 'comfort', 'television', priorities: $this->priorities('secondary')),
                $this->field('cd_player', 'Radio / CD Player', 'comfort', 'cd_player', priorities: $this->priorities('secondary')),
                $this->field('dvd_player', 'DVD Player', 'comfort', 'dvd_player', priorities: $this->priorities('secondary')),
                $this->field('satellite_reception', 'Satellite Reception', 'comfort', 'satellite_reception', priorities: $this->priorities('secondary')),
                $this->field('water_tank', 'Water Tank', 'comfort', 'water_tank', priorities: $this->priorities('primary')),
                $this->field('water_tank_gauge', 'Water Tank Gauge', 'comfort', 'water_tank_gauge', priorities: $this->priorities('secondary')),
                $this->field('water_maker', 'Water Maker', 'comfort', 'water_maker', priorities: $this->priorities('secondary')),
                $this->field('waste_water_tank', 'Waste Water Tank', 'comfort', 'waste_water_tank', priorities: $this->priorities('secondary')),
                $this->field('waste_water_tank_gauge', 'Waste Water Gauge', 'comfort', 'waste_water_tank_gauge', priorities: $this->priorities('secondary')),
                $this->field('waste_water_tank_drainpump', 'Waste Tank Drain Pump', 'comfort', 'waste_water_tank_drainpump', priorities: $this->priorities('secondary')),
                $this->field('deck_suction', 'Deck Suction', 'comfort', 'deck_suction', priorities: $this->priorities('secondary')),
                $this->field('water_system', 'Water System', 'comfort', 'water_system', priorities: $this->priorities('primary')),
                $this->field('hot_water', 'Hot Water', 'comfort', 'hot_water', priorities: $this->priorities('primary')),
                $this->field('sea_water_pump', 'Sea Water Pump', 'comfort', 'sea_water_pump', priorities: $this->priorities('secondary')),
                $this->field('deck_wash_pump', 'Deck Wash Pump', 'comfort', 'deck_wash_pump', priorities: $this->priorities('secondary')),
                $this->field('deck_shower', 'Deck Shower', 'comfort', 'deck_shower', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
                $this->field('hot_air', 'Hot Air Heating', 'comfort', 'hot_air', priorities: $this->priorities('secondary')),
                $this->field('stove', 'Stove Heating', 'comfort', 'stove', priorities: $this->priorities('secondary')),
                $this->field('central_heating', 'Central Heating', 'comfort', 'central_heating', priorities: $this->priorities('secondary')),
            ]),
            $this->buildBlock('deck_equipment', [
                $this->field('anchor', 'Anchor', 'deckEquipment', 'anchor', priorities: $this->priorities('primary')),
                $this->field('bow_thruster', 'Bow Thruster', 'engine', 'bow_thruster', fieldType: 'tri_state', priorities: $this->priorities('secondary', ['motorboat' => 'primary'])),
                $this->field('anchor_winch', 'Anchor Winch', 'deckEquipment', 'anchor_winch', priorities: $this->priorities('primary')),
                $this->field('spray_hood', 'Spray Hood', 'deckEquipment', 'spray_hood', priorities: $this->priorities('secondary', ['sailboat' => 'primary'])),
                $this->field('bimini', 'Bimini', 'deckEquipment', 'bimini', priorities: $this->priorities('primary')),
                $this->field('swimming_platform', 'Swimming Platform', 'deckEquipment', 'swimming_platform', priorities: $this->priorities('primary')),
                $this->field('swimming_ladder', 'Swimming Ladder', 'deckEquipment', 'swimming_ladder', priorities: $this->priorities('secondary')),
                $this->field('teak_deck', 'Teak Deck', 'deckEquipment', 'teak_deck', priorities: $this->priorities('secondary')),
                $this->field('cockpit_table', 'Cockpit Table', 'deckEquipment', 'cockpit_table', priorities: $this->priorities('secondary')),
                $this->field('dinghy', 'Dinghy', 'deckEquipment', 'dinghy', priorities: $this->priorities('secondary')),
                $this->field('trailer', 'Trailer', 'deckEquipment', 'trailer', fieldType: 'tri_state', priorities: $this->priorities('secondary')),
                $this->field('covers', 'Covers', 'deckEquipment', 'covers', priorities: $this->priorities('secondary')),
                $this->field('fenders', 'Fenders & Lines', 'deckEquipment', 'fenders', priorities: $this->priorities('secondary')),
                $this->field('anchor_connection', 'Anchor Connection', 'deckEquipment', 'anchor_connection', priorities: $this->priorities('secondary')),
                $this->field('stern_anchor', 'Stern Anchor', 'deckEquipment', 'stern_anchor', priorities: $this->priorities('secondary')),
                $this->field('spud_pole', 'Spud Pole', 'deckEquipment', 'spud_pole', priorities: $this->priorities('secondary')),
                $this->field('cockpit_tent', 'Cockpit Tent', 'deckEquipment', 'cockpit_tent', priorities: $this->priorities('secondary')),
                $this->field('outdoor_cushions', 'Outdoor Cushions', 'deckEquipment', 'outdoor_cushions', priorities: $this->priorities('secondary')),
                $this->field('sea_rails', 'Sea Rails', 'deckEquipment', 'sea_rails', priorities: $this->priorities('secondary')),
                $this->field('pushpit_pullpit', 'Pushpit / Pullpit', 'deckEquipment', 'pushpit_pullpit', priorities: $this->priorities('secondary')),
                $this->field('sail_lowering_system', 'Sail Lowering System', 'deckEquipment', 'sail_lowering_system', priorities: $this->priorities('secondary', ['sailboat' => 'primary'])),
                $this->field('crutch', 'Crutch (Schaar)', 'deckEquipment', 'crutch', priorities: $this->priorities('secondary')),
                $this->field('dinghy_brand', 'Dinghy Brand', 'deckEquipment', 'dinghy_brand', priorities: $this->priorities('secondary')),
                $this->field('outboard_engine', 'Outboard Engine', 'deckEquipment', 'outboard_engine', priorities: $this->priorities('secondary')),
                $this->field('crane', 'Crane', 'deckEquipment', 'crane', priorities: $this->priorities('secondary')),
                $this->field('davits', 'Davits', 'deckEquipment', 'davits', priorities: $this->priorities('secondary')),
            ]),
            $this->buildBlock('rigging', [
                $this->field('sailplan_type', 'Sailplan Type', 'rigging', 'sailplan_type', priorities: $this->priorities(null, ['sailboat' => 'primary', 'catamaran' => 'primary'])),
                $this->field('number_of_masts', 'Number of Masts', 'rigging', 'number_of_masts', fieldType: 'number', priorities: $this->priorities(null, ['sailboat' => 'primary', 'catamaran' => 'primary'])),
                $this->field('spars_material', 'Spars Material', 'rigging', 'spars_material', priorities: $this->priorities(null, ['sailboat' => 'secondary', 'catamaran' => 'secondary'])),
                $this->field('bowsprit', 'Bowsprit', 'rigging', 'bowsprit', priorities: $this->priorities(null, ['sailboat' => 'secondary', 'catamaran' => 'secondary'])),
                $this->field('standing_rig', 'Standing Rig', 'rigging', 'standing_rig', priorities: $this->priorities(null, ['sailboat' => 'secondary', 'catamaran' => 'secondary'])),
                $this->field('main_sail', 'Main Sail', 'rigging', 'main_sail', priorities: $this->priorities(null, ['sailboat' => 'primary', 'catamaran' => 'primary'])),
                $this->field('furling_mainsail', 'Furling Mainsail', 'rigging', 'furling_mainsail', priorities: $this->priorities(null, ['sailboat' => 'secondary', 'catamaran' => 'secondary'])),
                $this->field('jib', 'Jib', 'rigging', 'jib', priorities: $this->priorities(null, ['sailboat' => 'primary', 'catamaran' => 'primary'])),
                $this->field('genoa', 'Genoa', 'rigging', 'genoa', priorities: $this->priorities(null, ['sailboat' => 'primary', 'catamaran' => 'secondary'])),
                $this->field('spinnaker', 'Spinnaker', 'rigging', 'spinnaker', priorities: $this->priorities(null, ['sailboat' => 'secondary', 'catamaran' => 'secondary'])),
                $this->field('gennaker', 'Gennaker', 'rigging', 'gennaker', priorities: $this->priorities(null, ['sailboat' => 'secondary', 'catamaran' => 'secondary'])),
                $this->field('mizzen', 'Mizzen', 'rigging', 'mizzen', priorities: $this->priorities(null, ['sailboat' => 'secondary'])),
                $this->field('winches', 'Winches', 'rigging', 'winches', priorities: $this->priorities(null, ['sailboat' => 'primary', 'catamaran' => 'primary'])),
                $this->field('electric_winches', 'Electric Winches', 'rigging', 'electric_winches', priorities: $this->priorities(null, ['sailboat' => 'secondary', 'catamaran' => 'secondary'])),
                $this->field('manual_winches', 'Manual Winches', 'rigging', 'manual_winches', priorities: $this->priorities(null, ['sailboat' => 'secondary', 'catamaran' => 'secondary'])),
            ]),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function buildBlock(string $blockKey, array $fields): array
    {
        return collect($fields)
            ->values()
            ->map(function (array $field, int $index) use ($blockKey) {
                $field['block_key'] = $blockKey;
                $field['step_key'] = 'specs';
                $field['sort_order'] = ($index + 1) * 10;

                return $field;
            })
            ->all();
    }

    /**
     * @param  array<string, string>  $labels
     * @param  array<int, array<string, mixed>>  $priorities
     * @param  array<int, array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function field(
        string $internalKey,
        string $label,
        ?string $storageRelation,
        string $storageColumn,
        string $fieldType = 'text',
        ?array $labels = null,
        array $priorities = [],
        array $options = [],
        bool $aiRelevance = true,
    ): array {
        return [
            'internal_key' => $internalKey,
            'labels_json' => $labels ?? $this->labels($label),
            'options_json' => $options,
            'field_type' => $fieldType,
            'storage_relation' => $storageRelation,
            'storage_column' => $storageColumn,
            'priorities' => $priorities !== [] ? $priorities : $this->priorities('primary'),
            'ai_relevance' => $aiRelevance,
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<int, array<string, string>>
     */
    private function priorities(?string $defaultPriority, array $overrides = []): array
    {
        $priorities = [];

        if ($defaultPriority !== null) {
            $priorities[] = [
                'boat_type_key' => 'default',
                'priority' => $defaultPriority,
            ];
        }

        foreach ($overrides as $boatTypeKey => $priority) {
            $priorities[] = [
                'boat_type_key' => BoatFieldPriority::normalizeBoatTypeKey($boatTypeKey),
                'priority' => $priority,
            ];
        }

        return $priorities;
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $english, ?string $dutch = null, ?string $german = null): array
    {
        return [
            'en' => $english,
            'nl' => $dutch ?? $english,
            'de' => $german ?? $english,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function option(string $value, string $label, ?array $labels = null): array
    {
        return [
            'value' => $value,
            'label' => $label,
            'labels' => $labels ?? $this->labels($label),
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $priorities
     */
    private function syncPriorities(BoatField $field, array $priorities): void
    {
        $field->priorities()->delete();

        if ($priorities !== []) {
            $field->priorities()->createMany($priorities);
        }
    }
}
