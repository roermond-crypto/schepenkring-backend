<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarborChatSetting extends Model
{
    protected $table = 'harbor_chat_settings';

    protected $fillable = [
        'harbor_id',
        'ai_enabled',
        'ai_mode_default',
        'business_hours_start',
        'business_hours_end',
        'timezone',
        'first_response_minutes',
        'escalation_minutes',
        'offline_message',
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
    ];

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }
}
