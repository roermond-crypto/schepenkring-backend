<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'location_id',
        'parent_template_id',
        'language',
        'content_html',
        'content_json',
        'is_global',
        'is_default',
        'is_active',
        'is_archived',
        'description',
        'created_by_id',
        'updated_by_id',
        'current_version',
    ];

    protected $casts = [
        'content_json' => 'array',
        'is_global'    => 'boolean',
        'is_default'   => 'boolean',
        'is_active'    => 'boolean',
        'is_archived'  => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractTemplateVersion::class)->orderByDesc('version');
    }

    public function parentTemplate(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'parent_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
