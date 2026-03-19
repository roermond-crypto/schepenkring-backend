<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'location_id',
        'boat_id',
        'type',
        'status',
        'date',
        'time',
        'duration_minutes',
        'name',
        'email',
        'source',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'duration_minutes' => 'integer',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function boat()
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }
}
