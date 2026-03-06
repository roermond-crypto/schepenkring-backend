<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtAccommodation extends Model
{
    public $timestamps = false;

    protected $table = 'yacht_accommodation';

    protected $fillable = [
        'yacht_id', 'cabins', 'berths', 'toilet', 'shower', 'bath',
        'interior_type', 'saloon', 'headroom', 'separate_dining_area',
        'engine_room', 'spaces_inside', 'upholstery_color', 'matrasses',
        'cushions', 'curtains', 'berths_fixed', 'berths_extra', 'berths_crew',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
