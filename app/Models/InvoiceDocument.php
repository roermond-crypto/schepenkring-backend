<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceDocument extends Model
{
    use HasFactory;

    public const TYPE_INCOMING = 'incoming';
    public const TYPE_OUTGOING = 'outgoing';

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_VOID = 'void';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_CREDITED = 'credited';

    protected $fillable = [
        'type',
        'status',
        'storage_disk',
        'storage_path',
        'source_filename',
        'file_hash',
        'hash_algo',
        'file_size',
        'mime_type',
        'retention_until',
        'raw_text',
        'ocr_json',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'retention_until' => 'date',
        'ocr_json' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (InvoiceDocument $document) {
            $immutable = [
                'storage_disk',
                'storage_path',
                'source_filename',
                'file_hash',
                'hash_algo',
                'file_size',
                'mime_type',
                'retention_until',
            ];

            foreach ($immutable as $field) {
                if ($document->isDirty($field)) {
                    throw new \RuntimeException("Immutable invoice field cannot be changed: {$field}");
                }
            }
        });

        static::deleting(function () {
            throw new \RuntimeException('Invoice documents are immutable and cannot be deleted.');
        });
    }

    public function fields(): HasOne
    {
        return $this->hasOne(InvoiceField::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(InvoiceStatusHistory::class)->orderBy('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
