<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FaqTranslation extends Model
{
    use HasUuids;

    protected $table = 'faq_translation';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'faq_id',
        'language',
        'question',
        'answer',
        'views',
        'helpful',
        'not_helpful',
        'long_description',
        'long_description_status',
        'needs_review',
        'translation_status',
        'source_hash',
        'translated_from_hash',
        'is_legal',
        'source_language',
        'translated_from_translation_id',
        'indexed_at',
    ];

    protected $casts = [
        'needs_review' => 'boolean',
        'is_legal' => 'boolean',
        'indexed_at' => 'datetime',
        'views' => 'integer',
        'helpful' => 'integer',
        'not_helpful' => 'integer',
    ];

    public function faq()
    {
        return $this->belongsTo(FaqEntry::class, 'faq_id');
    }
}
