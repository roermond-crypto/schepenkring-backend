<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAutomationTemplateItem extends Model
{
    protected $fillable = [
        'template_id',
        'title',
        'description',
        'priority',
        'position',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskAutomationTemplate::class, 'template_id');
    }
}
