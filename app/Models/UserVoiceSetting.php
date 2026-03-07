<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVoiceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tts_voice_id',
        'tts_enabled',
        'stt_language',
        'speaking_rate',
        'pitch',
    ];

    protected $casts = [
        'tts_enabled' => 'boolean',
        'speaking_rate' => 'decimal:2',
        'pitch' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
