<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class KycDocument extends Model
{
    protected $table = 'kyc_documents';

    protected $fillable = [
        'kyc_case_id', 'party', 'document_type',
        'original_name', 'path', 'mime_type', 'size', 'disk',
        'uploaded_by', 'verified', 'verified_by', 'verified_at', 'ocr_data',
    ];

    protected $casts = [
        'verified'    => 'boolean',
        'verified_at' => 'datetime',
        'ocr_data'    => 'array',
        'size'        => 'integer',
    ];

    public function kycCase(): BelongsTo   { return $this->belongsTo(KycCase::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk ?? 'private')->temporaryUrl($this->path, now()->addMinutes(30));
    }
}
