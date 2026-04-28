<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerVerificationFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_verification_id',
        'flag_code',
        'severity',
        'message',
        'metadata_json',
        'is_blocking',
    ];

    protected $casts = [
        'metadata_json' => 'json',
        'is_blocking' => 'boolean',
    ];
}
