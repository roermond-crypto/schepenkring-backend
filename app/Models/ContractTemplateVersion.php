<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractTemplateVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'contract_template_id',
        'version',
        'content_html',
        'content_json',
        'change_note',
        'created_by_id',
    ];

    protected $casts = [
        'content_json' => 'array',
        'created_at'   => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }
}
