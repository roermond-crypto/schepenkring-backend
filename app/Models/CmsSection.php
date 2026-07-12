<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsSection extends Model
{
    protected $fillable = [
        'cms_page_id',
        'component',
        'variant',
        'content',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'content' => 'array',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }
}
