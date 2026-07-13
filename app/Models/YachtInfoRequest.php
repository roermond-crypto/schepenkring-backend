<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YachtInfoRequest extends Model
{
    protected $fillable = [
        'yacht_id',
        'requested_by_id',
        'items',
        'status',
        'resolved_by_id',
        'resolved_at',
    ];

    protected $casts = [
        'items' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
