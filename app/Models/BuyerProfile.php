<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'buyer_type',
        'full_name',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'formatted_address',
        'street',
        'house_number',
        'latitude',
        'longitude',
        'place_id',
        'city',
        'state',
        'postal_code',
        'country',
        'birth_date',
        'iban',
        'company_name',
        'kvk_number',
        'verified_full_name',
        'verified_iban',
        'verified_bank_account_holder',
        'identity_verified_at',
        'bank_verified_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'identity_verified_at' => 'datetime',
        'bank_verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
