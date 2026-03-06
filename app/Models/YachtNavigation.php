<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtNavigation extends Model
{
    public $timestamps = false;

    protected $table = 'yacht_navigation';

    protected $fillable = [
        'yacht_id', 'navigation_lights', 'compass', 'depth_instrument',
        'wind_instrument', 'autopilot', 'gps', 'vhf', 'plotter',
        'speed_instrument', 'radar', 'log_speed', 'windvane_steering',
        'charts_guides', 'rudder_position_indicator', 'fishfinder',
        'turn_indicator', 'ais', 'ssb_receiver', 'shortwave_radio',
        'short_band_transmitter', 'weatherfax_navtex', 'satellite_communication',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
