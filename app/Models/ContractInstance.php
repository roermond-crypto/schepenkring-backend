<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractInstance extends Model
{
    protected $fillable = [
        'sign_request_id',
        'contract_template_id',
        'template_version_used',
        'content_html',
        'content_json',
        'rendered_html',
        'yacht_id',
        'location_id',
        'tags_data',
        'pdf_path',
        'pdf_sha256',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'content_json' => 'array',
        'tags_data'    => 'array',
    ];

    public function signRequest(): BelongsTo
    {
        return $this->belongsTo(SignRequest::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}
