<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtDeckEquipment extends Model
{
    public $timestamps = false;

    protected $table = 'yacht_deck_equipment';

    protected $fillable = [
        'yacht_id', 'anchor', 'spray_hood', 'bimini', 'fenders',
        'anchor_connection', 'anchor_winch', 'stern_anchor', 'spud_pole',
        'cockpit_tent', 'outdoor_cushions', 'covers', 'sea_rails',
        'pushpit_pullpit', 'swimming_platform', 'swimming_ladder',
        'sail_lowering_system', 'crutch', 'dinghy', 'dinghy_brand',
        'outboard_engine', 'trailer', 'crane', 'davits', 'teak_deck',
        'cockpit_table', 'oars_paddles',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
