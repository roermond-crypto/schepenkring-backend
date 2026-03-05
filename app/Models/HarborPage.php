<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HarborPage extends Model
{
    protected $fillable = [
        'harbor_id',
        'locale',
        'page_content',
        'generated_at',
        'source_data_hash',
        'translation_status',
        'translated_from_hash',
    ];

    protected $casts = [
        'page_content' => 'array',
        'generated_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────

    public function harbor(): BelongsTo
    {
        return $this->belongsTo(Harbor::class);
    }
}
