<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractParty extends Model
{
    protected $fillable = [
        'role_type',
        'name',
        'company_name',
        'address',
        'postal_code',
        'city',
        'phone',
        'email',
        'passport_number',
        'partner_name',
        'married',
        'location_id',
    ];

    protected function casts(): array
    {
        return [
            'married' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
