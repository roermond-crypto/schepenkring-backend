<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'message_id',
        'storage_key',
        'mime_type',
        'size',
        'checksum',
        'extracted_text',
        'ai_summary',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
