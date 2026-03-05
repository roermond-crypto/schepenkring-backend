<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'place_id',
        'company_name',
        'street',
        'postal_code',
        'city',
        'country',
        'latitude',
        'longitude',
        'agreement_version',
        'agreement_accepted_at',
        'contract_signed_at',
    ];

    protected $casts = [
        'agreement_accepted_at' => 'datetime',
        'contract_signed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
