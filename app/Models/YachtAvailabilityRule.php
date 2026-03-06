<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YachtAvailabilityRule extends Model
{
    // Explicitly define the table name from your migration
    protected $table = 'yacht_availability_rules';

    protected $fillable = [
        'yacht_id',
        'day_of_week',
        'start_time',
        'end_time'
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}