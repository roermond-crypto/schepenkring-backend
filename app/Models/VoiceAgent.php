<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceAgent extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'language',
        'purpose',
        'retell_agent_id',
        'voice',
        'model',
        'prompt',
        'calling_hours',
        'retry_rules',
        'spend_cap_eur',
        'status',
        'knowledge_categories',
    ];

    protected $casts = [
        'calling_hours' => 'array',
        'retry_rules' => 'array',
        'knowledge_categories' => 'array',
        'spend_cap_eur' => 'decimal:2',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
