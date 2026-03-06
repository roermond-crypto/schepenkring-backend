<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtRigging extends Model
{
    public $timestamps = false;

    protected $table = 'yacht_rigging';

    protected $fillable = [
        'yacht_id', 'sailplan_type', 'number_of_masts', 'spars_material',
        'bowsprit', 'standing_rig', 'sail_surface_area', 'stabilizer_sail',
        'sail_amount', 'sail_material', 'sail_manufacturer', 'genoa',
        'main_sail', 'furling_mainsail', 'tri_sail', 'storm_jib', 'mizzen',
        'furling_mizzen', 'jib', 'roller_furling_foresail', 'genoa_reefing_system',
        'flying_jib', 'spinnaker', 'halfwinder_bollejan', 'gennaker', 'winches',
        'electric_winches', 'manual_winches', 'hydraulic_winches',
        'self_tailing_winches',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
