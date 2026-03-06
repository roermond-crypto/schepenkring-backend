<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtElectrical extends Model
{
    public $timestamps = false;

    protected $table = 'yacht_electrical';

    protected $fillable = [
        'yacht_id', 'battery', 'battery_charger', 'generator', 'inverter',
        'dynamo', 'accumonitor', 'voltmeter', 'shorepower', 'shore_power_cable',
        'wind_generator', 'solar_panel', 'consumption_monitor', 'control_panel',
        'voltage', 'fuel_tank_gauge', 'tachometer', 'oil_pressure_gauge',
        'temperature_gauge',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
