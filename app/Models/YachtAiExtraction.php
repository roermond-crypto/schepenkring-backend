<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YachtAiExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'yacht_id',
        'user_id',
        'status',
        'model_name',
        'model_version',
        'hint_text',
        'image_count',
        'raw_output_json',
        'normalized_fields_json',
        'field_confidence_json',
        'field_sources_json',
        'meta_json',
        'extracted_at',
    ];

    protected $casts = [
        'image_count' => 'integer',
        'raw_output_json' => 'array',
        'normalized_fields_json' => 'array',
        'field_confidence_json' => 'array',
        'field_sources_json' => 'array',
        'meta_json' => 'array',
        'extracted_at' => 'datetime',
    ];

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

