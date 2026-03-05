<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'deal_id',
        'type',
        'mollie_payment_id',
        'idempotency_key',
        'amount_currency',
        'amount_value',
        'status',
        'checkout_url',
        'webhook_events_count',
    ];

    protected $casts = [
        'amount_value' => 'decimal:2',
        'webhook_events_count' => 'integer',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
