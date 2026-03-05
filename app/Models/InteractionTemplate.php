<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InteractionTemplate extends Model
{
    protected $fillable = [
        'event_type_id',
        'channel',
        'name',
        'source_locale',
        'source_hash',
        'subject',
        'body',
        'version',
        'is_active',
        'placeholders',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'placeholders' => 'array',
    ];

    public function eventType()
    {
        return $this->belongsTo(InteractionEventType::class, 'event_type_id');
    }

    public function translations()
    {
        return $this->hasMany(InteractionTemplateTranslation::class);
    }
}
