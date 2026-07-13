<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenMarineFieldMappingVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'version',
        'mappings_snapshot',
        'change_note',
        'created_by_id',
        'created_at',
    ];

    protected $casts = [
        'mappings_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
