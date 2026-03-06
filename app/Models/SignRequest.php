<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SignRequest extends Model
{
    protected $fillable = [
        'location_id',
        'entity_type',
        'entity_id',
        'provider',
        'status',
        'signhost_transaction_id',
        'sign_url',
        'requested_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(SignDocument::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
