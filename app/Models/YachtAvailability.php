<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YachtAvailability extends Model
{
    protected $fillable = [
        'yacht_id',
        'day_of_week',
        'start_time',
        'end_time'
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}