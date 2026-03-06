<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtComfort extends Model
{
    public $timestamps = false;

    protected $table = 'yacht_comfort';

    protected $fillable = [
        'yacht_id', 'oven', 'microwave', 'fridge', 'freezer', 'heating',
        'air_conditioning', 'cooker', 'cooking_fuel', 'hot_air', 'stove',
        'central_heating', 'satellite_reception', 'television', 'cd_player',
        'dvd_player', 'water_tank', 'water_tank_material', 'water_tank_gauge',
        'water_maker', 'waste_water_tank', 'waste_water_tank_material',
        'waste_water_tank_gauge', 'waste_water_tank_drainpump', 'deck_suction',
        'water_system', 'hot_water', 'sea_water_pump', 'deck_wash_pump',
        'deck_shower',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
