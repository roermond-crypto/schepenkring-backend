<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatFieldChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'yacht_id',
        'field_name',
        'old_value',
        'new_value',
        'changed_by_type',
        'changed_by_id',
        'source_type',
        'confidence_before',
        'ai_session_id',
        'model_name',
        'reason',
        'correction_label',
        'meta',
    ];

    protected $casts = [
        'confidence_before' => 'float',
        'meta' => 'array',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}

