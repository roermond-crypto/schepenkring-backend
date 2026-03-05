<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    protected $fillable = [
        'seller_user_id',
        'buyer_user_id',
        'boat_id',
        'status',
        'contract_pdf_path',
        'contract_sha256',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }

    public function signhostTransactions(): HasMany
    {
        return $this->hasMany(SignhostTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
