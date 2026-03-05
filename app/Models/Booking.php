<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model {
    // app/Models/Booking.php
    protected $fillable = [
        'yacht_id',
        'harbor_id',
        'user_id',
        'seller_user_id',
        'bid_id',
        'deal_id',
        'start_at',
        'end_at',
        'location',
        'type',
        'status'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }
}
