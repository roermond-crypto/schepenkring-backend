<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InteractionTemplateTranslation extends Model
{
    protected $fillable = [
        'interaction_template_id',
        'locale',
        'subject',
        'body',
        'status',
        'source_hash',
        'translated_from_hash',
        'is_legal',
    ];

    protected $casts = [
        'is_legal' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(InteractionTemplate::class, 'interaction_template_id');
    }
}
