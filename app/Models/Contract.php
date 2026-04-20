<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_user_id',
        'seller_user_id',
        'boat_id',
        'signhost_envelope_id',
        'status',
        'metadata',
        'file_path',
        'signed_by_buyer',
        'signed_by_seller',
    ];

    protected $casts = [
        'metadata' => 'array',
        'signed_by_buyer' => 'boolean',
        'signed_by_seller' => 'boolean',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }
}
