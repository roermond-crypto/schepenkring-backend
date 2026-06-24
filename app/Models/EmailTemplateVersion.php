<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplateVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email_template_id',
        'version',
        'subject',
        'blocks',
        'change_note',
        'created_by_id',
    ];

    protected $casts = [
        'subject'    => 'array',
        'blocks'     => 'array',
        'created_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
