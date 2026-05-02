<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoTemplate extends Model
{
    protected $fillable = [
        'name', 'slug', 'video_type', 'is_active', 'is_default',
        'settings_json', 'ai_rules_json', 'created_by',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'is_default'    => 'boolean',
        'settings_json' => 'array',
        'ai_rules_json' => 'array',
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(VideoPlan::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
