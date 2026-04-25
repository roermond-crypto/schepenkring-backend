<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatChannelLog extends Model
{
    protected $fillable = [
        'boat_id',
        'boat_channel_listing_id',
        'channel_name',
        'action',
        'status',
        'request_payload_json',
        'response_payload_json',
        'error_message',
    ];

    protected $casts = [
        'request_payload_json' => 'array',
        'response_payload_json' => 'array',
    ];

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(BoatChannelListing::class, 'boat_channel_listing_id');
    }
}
