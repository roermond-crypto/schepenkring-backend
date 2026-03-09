<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class YachtDraft extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'draft_id',
        'yacht_id',
        'status',
        'wizard_step',
        'payload_json',
        'ui_state_json',
        'images_manifest_json',
        'ai_state_json',
        'version',
        'last_client_saved_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'ui_state_json' => 'array',
        'images_manifest_json' => 'array',
        'ai_state_json' => 'array',
        'wizard_step' => 'integer',
        'version' => 'integer',
        'last_client_saved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}

