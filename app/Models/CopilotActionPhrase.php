<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopilotActionPhrase extends Model
{
    use HasFactory;

    protected $fillable = [
        'copilot_action_id',
        'phrase',
        'language',
        'priority',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'priority' => 'integer',
        'enabled' => 'boolean',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(CopilotAction::class, 'copilot_action_id');
    }
}
