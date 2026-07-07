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
        // Dedicated signhost columns
        'signhost_buyer_link',
        'signhost_seller_link',
        'signhost_created_at',
        'signhost_expires_at',
        'signhost_last_checked_at',
        'buyer_signed_at',
        'seller_signed_at',
        'broker_signed_at',
        'completed_at',
        'signed_pdf_path',
        'signed_pdf_hash',
        'signhost_raw_response',
        'webhook_failed',
        'webhook_error',
        'webhook_last_payload',
    ];

    protected $casts = [
        'metadata'              => 'array',
        'signhost_raw_response' => 'array',
        'webhook_last_payload'  => 'array',
        'signhost_created_at'   => 'datetime',
        'signhost_expires_at'   => 'datetime',
        'signhost_last_checked_at' => 'datetime',
        'buyer_signed_at'       => 'datetime',
        'seller_signed_at'      => 'datetime',
        'broker_signed_at'      => 'datetime',
        'completed_at'          => 'datetime',
        'webhook_failed'        => 'boolean',
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

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'entity_id');
    }
}
