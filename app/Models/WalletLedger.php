<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLedger extends Model
{
    use HasFactory;

    public const TYPE_COMMISSION_PENDING = 'COMMISSION_PENDING';
    public const TYPE_COMMISSION_REALIZED = 'COMMISSION_REALIZED';
    public const TYPE_HARBOR_SPLIT = 'HARBOR_SPLIT';
    public const TYPE_LISTING_FEE = 'LISTING_FEE';
    public const TYPE_REFUND = 'REFUND';
    public const TYPE_PAYOUT = 'PAYOUT';
    public const TYPE_CORRECTION = 'CORRECTION';
    public const TYPE_LOCKED = 'LOCKED';
    public const TYPE_VOICE_USAGE = 'VOICE_USAGE';
    public const TYPE_TOPUP = 'TOPUP';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'currency',
        'reference_type',
        'reference_id',
        'reference_key',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Wallet ledger entries are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Wallet ledger entries are immutable.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
