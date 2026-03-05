<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtSafety extends Model
{
    public $timestamps = false;

    protected $table = 'yacht_safety';

    protected $fillable = [
        'yacht_id', 'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher',
        'mob_system', 'life_buoy', 'bilge_pump_manual', 'bilge_pump_electric',
        'radar_reflector', 'flares', 'life_jackets', 'watertight_door',
        'gas_bottle_locker', 'self_draining_cockpit',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
