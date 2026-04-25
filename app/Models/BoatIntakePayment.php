<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatIntakePayment extends Model
{
    protected $fillable = [
        'boat_intake_id',
        'user_id',
        'mollie_payment_id',
        'idempotency_key',
        'status',
        'amount_currency',
        'amount_value',
        'checkout_url',
        'redirect_url',
        'webhook_events_count',
        'metadata_json',
        'paid_at',
    ];

    protected $casts = [
        'amount_value' => 'decimal:2',
        'metadata_json' => 'array',
        'paid_at' => 'datetime',
    ];

    public function intake(): BelongsTo
    {
        return $this->belongsTo(BoatIntake::class, 'boat_intake_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
