<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtConstruction extends Model
{
    public $timestamps = false;

    protected $table = 'yacht_construction';

    protected $fillable = [
        'yacht_id', 'designer', 'builder', 'where', 'hull_colour',
        'hull_construction', 'hull_number', 'hull_type',
        'super_structure_colour', 'super_structure_construction',
        'deck_colour', 'deck_construction', 'windows',
        'cockpit_type', 'control_type', 'flybridge',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
