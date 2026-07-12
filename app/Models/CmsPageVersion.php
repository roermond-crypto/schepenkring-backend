<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPageVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cms_page_id',
        'version',
        'snapshot',
        'change_note',
        'created_by_id',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
