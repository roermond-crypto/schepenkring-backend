<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceField extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_document_id',
        'extracted_fields',
        'field_confidence',
        'normalized_fields',
        'approved_fields',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'extracted_fields' => 'array',
        'field_confidence' => 'array',
        'normalized_fields' => 'array',
        'approved_fields' => 'array',
        'approved_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(InvoiceDocument::class, 'invoice_document_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
