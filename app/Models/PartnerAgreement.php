<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerAgreement extends Model
{
    protected $fillable = [
        'user_id',
        'agreement_version',
        'agreement_text',
        'agreement_hash',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
