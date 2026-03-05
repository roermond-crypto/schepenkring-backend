<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InteractionEventType extends Model
{
    protected $fillable = [
        'category_id',
        'key',
        'name',
        'description',
        'default_channels',
        'enabled',
    ];

    protected $casts = [
        'default_channels' => 'array',
        'enabled' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(InteractionEventCategory::class, 'category_id');
    }

    public function templates()
    {
        return $this->hasMany(InteractionTemplate::class, 'event_type_id');
    }
}
