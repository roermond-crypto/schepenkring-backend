<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtDimensions extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'yacht_id', 'beam', 'draft', 'loa', 'lwl', 'air_draft',
        'displacement', 'ballast', 'passenger_capacity',
        'minimum_height', 'variable_depth', 'max_draft', 'min_draft',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
