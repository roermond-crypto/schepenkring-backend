<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTopup extends Model
{
    protected $fillable = [
        'user_id',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
