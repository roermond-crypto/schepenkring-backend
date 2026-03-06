<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtEngine extends Model
{
    public $timestamps = false;

    protected $table = 'yacht_engines';

    protected $fillable = [
        'yacht_id', 'engine_manufacturer', 'horse_power', 'hours', 'fuel',
        'max_speed', 'cruising_speed', 'engine_quantity', 'tankage',
        'gallons_per_hour', 'litres_per_hour', 'engine_location', 'gearbox',
        'cylinders', 'propeller_type', 'starting_type', 'drive_type',
        'cooling_system', 'engine_model', 'engine_serial_number', 'engine_type',
        'engine_year', 'reversing_clutch', 'transmission', 'propulsion',
        'motorization_summary', 'fuel_tanks_amount', 'fuel_tank_total_capacity',
        'fuel_tank_material', 'range_km', 'stern_thruster', 'bow_thruster',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
