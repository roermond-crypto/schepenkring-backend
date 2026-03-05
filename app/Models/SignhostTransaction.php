<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignhostTransaction extends Model
{
    protected $fillable = [
        'deal_id',
        'signhost_transaction_id',
        'status',
        'signing_url_buyer',
        'signing_url_seller',
        'signed_pdf_path',
        'webhook_last_payload',
    ];

    protected $casts = [
        'webhook_last_payload' => 'array',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
