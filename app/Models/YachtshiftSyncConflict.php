<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YachtshiftSyncConflict extends Model
{
    protected $fillable = [
        'yacht_id',
        'external_id',
        'field_name',
        'local_value',
        'remote_value',
        'status',
        'resolution',
        'resolved_by_id',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
