<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoatInspection extends Model
{
    protected $fillable = ['boat_id', 'user_id', 'status'];

    protected $casts = [
        'status' => 'string',
    ];

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Yacht::class, 'boat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(InspectionAnswer::class, 'inspection_id');
    }
}