<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BoatIntake extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'brand',
        'model',
        'year',
        'length_m',
        'width_m',
        'height_m',
        'fuel_type',
        'price',
        'description',
        'boat_type',
        'photo_manifest_json',
        'latest_payment_id',
        'listing_workflow_id',
        'submitted_at',
        'paid_at',
    ];

    protected $casts = [
        'photo_manifest_json' => 'array',
        'price' => 'decimal:2',
        'length_m' => 'decimal:2',
        'width_m' => 'decimal:2',
        'height_m' => 'decimal:2',
        'submitted_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BoatIntakePayment::class);
    }

    public function latestPayment(): BelongsTo
    {
        return $this->belongsTo(BoatIntakePayment::class, 'latest_payment_id');
    }

    public function listingWorkflow(): HasOne
    {
        return $this->hasOne(ListingWorkflow::class);
    }
}
