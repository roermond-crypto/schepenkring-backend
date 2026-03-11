<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use App\Traits\Auditable;

class Yacht extends Model
{
    use Auditable;

    // Used by saveSubTables() and toArray() to keep things DRY.
    public const SUB_TABLE_MAP = [
        'dimensions' => [
            'beam', 'draft', 'loa', 'lwl', 'air_draft', 'displacement',
            'ballast', 'passenger_capacity', 'minimum_height', 'variable_depth',
            'max_draft', 'min_draft',
        ],
        'construction' => [
            'designer', 'builder', 'where', 'hull_colour', 'hull_construction',
            'hull_number', 'hull_type', 'super_structure_colour',
            'super_structure_construction', 'deck_colour', 'deck_construction',
            'windows', 'cockpit_type', 'control_type', 'flybridge',
        ],
        'accommodation' => [
            'cabins', 'berths', 'toilet', 'shower', 'bath',
            'interior_type', 'saloon', 'headroom', 'separate_dining_area',
            'engine_room', 'spaces_inside', 'upholstery_color', 'matrasses',
            'cushions', 'curtains', 'berths_fixed', 'berths_extra', 'berths_crew',
        ],
        'engine' => [
            'engine_manufacturer', 'horse_power', 'hours', 'fuel', 'max_speed',
            'cruising_speed', 'engine_quantity', 'tankage', 'gallons_per_hour',
            'litres_per_hour', 'engine_location', 'gearbox', 'cylinders',
            'propeller_type', 'starting_type', 'drive_type', 'cooling_system',
            'engine_model', 'engine_serial_number', 'engine_type', 'engine_year',
            'reversing_clutch', 'transmission', 'propulsion', 'motorization_summary',
            'fuel_tanks_amount', 'fuel_tank_total_capacity', 'fuel_tank_material',
            'range_km', 'stern_thruster', 'bow_thruster',
        ],
        'electrical' => [
            'battery', 'battery_charger', 'generator', 'inverter',
            'dynamo', 'accumonitor', 'voltmeter', 'shorepower', 'shore_power_cable',
            'wind_generator', 'solar_panel', 'consumption_monitor', 'control_panel',
            'voltage', 'fuel_tank_gauge', 'tachometer', 'oil_pressure_gauge',
            'temperature_gauge',
        ],
        'navigation' => [
            'navigation_lights', 'compass', 'depth_instrument', 'wind_instrument',
            'autopilot', 'gps', 'vhf', 'plotter', 'speed_instrument', 'radar',
            'log_speed', 'windvane_steering', 'charts_guides',
            'rudder_position_indicator', 'fishfinder', 'turn_indicator', 'ais',
            'ssb_receiver', 'shortwave_radio', 'short_band_transmitter',
            'weatherfax_navtex', 'satellite_communication',
        ],
        'safety' => [
            'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher', 'mob_system',
            'life_buoy', 'bilge_pump_manual', 'bilge_pump_electric',
            'radar_reflector', 'flares', 'life_jackets', 'watertight_door',
            'gas_bottle_locker', 'self_draining_cockpit',
        ],
        'comfort' => [
            'oven', 'microwave', 'fridge', 'freezer', 'heating', 'air_conditioning',
            'cooker', 'cooking_fuel', 'hot_air', 'stove', 'central_heating',
            'satellite_reception', 'television', 'cd_player', 'dvd_player',
            'water_tank', 'water_tank_material', 'water_tank_gauge', 'water_maker',
            'waste_water_tank', 'waste_water_tank_material', 'waste_water_tank_gauge',
            'waste_water_tank_drainpump', 'deck_suction', 'water_system', 'hot_water',
            'sea_water_pump', 'deck_wash_pump', 'deck_shower',
        ],
        'deckEquipment' => [
            'anchor', 'spray_hood', 'bimini', 'fenders',
            'anchor_connection', 'anchor_winch', 'stern_anchor', 'spud_pole',
            'cockpit_tent', 'outdoor_cushions', 'covers', 'sea_rails',
            'pushpit_pullpit', 'swimming_platform', 'swimming_ladder',
            'sail_lowering_system', 'crutch', 'dinghy', 'dinghy_brand',
            'outboard_engine', 'trailer', 'crane', 'davits', 'teak_deck',
            'cockpit_table', 'oars_paddles',
        ],
        'rigging' => [
            'sailplan_type', 'number_of_masts', 'spars_material', 'bowsprit',
            'standing_rig', 'sail_surface_area', 'stabilizer_sail', 'sail_amount',
            'sail_material', 'sail_manufacturer', 'genoa', 'main_sail',
            'furling_mainsail', 'tri_sail', 'storm_jib', 'mizzen', 'furling_mizzen',
            'jib', 'roller_furling_foresail', 'genoa_reefing_system', 'flying_jib',
            'spinnaker', 'halfwinder_bollejan', 'gennaker', 'winches',
            'electric_winches', 'manual_winches', 'hydraulic_winches',
            'self_tailing_winches',
        ],
    ];

    // ─── Only core columns remain on the yachts table ──────────
    protected $fillable = [
        // Core identity
        'user_id', 'booking_duration_minutes', 'vessel_id', 'boat_name', 'price', 'status',
        'allow_bidding', 'main_image', 'year', 'min_bid_amount',
        'current_bid', 'boat_type_id', 'display_specs', 'offline_uuid', 'ref_harbor_id',

        // Identity (from Yachtshift)
        'boat_type', 'boat_category', 'new_or_used', 'manufacturer', 'model',
        'vessel_lying', 'location_city', 'location_lat', 'location_lng',
        'short_description_nl', 'short_description_en', 'short_description_de', 'short_description_fr', 'advertise_as',
        'advertising_channels',

        // URLs and references
        'external_url', 'print_url', 'owners_comment', 'reg_details',
        'known_defects', 'last_serviced',

        // CE Certification
        'ce_category', 'ce_max_weight', 'ce_max_motor', 'cvo', 'cbb',

        // Hull & Structure (remaining on yachts)
        'open_cockpit', 'aft_cockpit', 'ballast_tank',
        'steering_system', 'steering_system_location',
        'remote_control', 'rudder', 'drift_restriction',
        'drift_restriction_controls', 'trimflaps', 'stabilizer',

        // Google Merchant Sync
        'google_offer_id', 'google_product_id', 'google_status',
        'google_last_sync_at', 'google_last_error',
    ];

    protected $casts = [
        'price' => 'float',
        'sale_price' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'harbor_split_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_calculated_at' => 'datetime',
        'year' => 'integer',
        'booking_duration_minutes' => 'integer',
        'min_bid_amount' => 'float',
        'advertising_channels' => 'array',
        'location_lat' => 'float',
        'location_lng' => 'float',
        'allow_bidding' => 'boolean',
    ];

    // Eager-load all sub-tables by default
    protected $with = [
        'dimensions', 'construction', 'accommodation', 'engine',
        'electrical', 'navigation', 'safety', 'comfort',
        'deckEquipment', 'rigging',
    ];

    // ─── Sub-table relationships (one-to-one) ──────────────────

    public function dimensions(): HasOne {
        return $this->hasOne(YachtDimensions::class);
    }

    public function construction(): HasOne {
        return $this->hasOne(YachtConstruction::class);
    }

    public function accommodation(): HasOne {
        return $this->hasOne(YachtAccommodation::class);
    }

    public function engine(): HasOne {
        return $this->hasOne(YachtEngine::class);
    }

    public function electrical(): HasOne {
        return $this->hasOne(YachtElectrical::class);
    }

    public function navigation(): HasOne {
        return $this->hasOne(YachtNavigation::class);
    }

    public function safety(): HasOne {
        return $this->hasOne(YachtSafety::class);
    }

    public function comfort(): HasOne {
        return $this->hasOne(YachtComfort::class);
    }

    public function deckEquipment(): HasOne {
        return $this->hasOne(YachtDeckEquipment::class);
    }

    public function rigging(): HasOne {
        return $this->hasOne(YachtRigging::class);
    }

    public function videoSetting(): HasOne {
        return $this->hasOne(BoatVideoSetting::class);
    }

    public function socialPosts(): HasMany {
        return $this->hasMany(SocialPost::class);
    }

    public function fieldChanges(): HasMany {
        return $this->hasMany(BoatFieldChange::class);
    }

    public function aiExtractions(): HasMany {
        return $this->hasMany(YachtAiExtraction::class);
    }

    // ─── Existing relationships ────────────────────────────────

    public function images(): HasMany {
        return $this->hasMany(YachtImage::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function availabilityRules(): HasMany {
        return $this->hasMany(\App\Models\YachtAvailabilityRule::class);
    }

    // ─── Flatten sub-tables into top-level JSON ────────────────
    // This keeps the API response backward-compatible: the frontend
    // still receives a flat object with all 228 fields.

    public function toArray()
    {
        $array = parent::toArray();

        // Merge each sub-table's attributes into the top level
        foreach (array_keys(self::SUB_TABLE_MAP) as $relation) {
            $snakeRelation = Str::snake($relation);
            if (isset($array[$snakeRelation]) && is_array($array[$snakeRelation])) {
                $sub = $array[$snakeRelation];
                unset($sub['id'], $sub['yacht_id']); // strip internal keys
                $array = array_merge($array, $sub);
            }
            unset($array[$snakeRelation]); // remove the nested key
        }

        return $array;
    }

    // ─── Save flat request data into sub-tables ────────────────
    // Called from YachtController after saving the core yacht.

    public function saveSubTables(array $data): void
    {
        foreach (self::SUB_TABLE_MAP as $relation => $fields) {
            $subData = [];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $value = $data[$field];
                    $subData[$field] = ($value === '' || $value === 'undefined') ? null : $value;
                }
            }

            // Only touch the sub-table if we have data for it
            if (!empty($subData)) {
                $this->{$relation}()->updateOrCreate(
                    ['yacht_id' => $this->id],
                    $subData
                );
            } else {
                // Ensure the sub-table row exists even with no data
                $this->{$relation}()->firstOrCreate(['yacht_id' => $this->id]);
            }
        }
    }

    // ─── Lifecycle ─────────────────────────────────────────────

    /**
     * Auto-generate a unique Vessel ID when creating a yacht.
     */
    protected static function booted()
    {
        static::creating(function ($yacht) {
            if (!$yacht->vessel_id) {
                $yacht->vessel_id = 'SK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            }
        });
    }
}
