<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantSyncLog extends Model
{
    protected $fillable = [
        'yacht_id',
        'action',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
}
