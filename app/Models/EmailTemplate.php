<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    protected $fillable = [
        'type',
        'name',
        'description',
        'is_global',
        'location_id',
        'parent_template_id',
        'language_default',
        'subject',
        'blocks',
        'sender_name_override',
        'sender_email_override',
        'reply_to_override',
        'primary_color_override',
        'is_active',
        'is_archived',
        'created_by_id',
        'current_version',
    ];

    protected $casts = [
        'subject'     => 'array',
        'blocks'      => 'array',
        'is_global'   => 'boolean',
        'is_active'   => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EmailTemplateVersion::class);
    }

    public function parentTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'parent_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
