<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopilotActionSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'suggestion_key',
        'suggestion_type',
        'target_copilot_action_id',
        'created_action_id',
        'action_id',
        'title',
        'short_description',
        'module',
        'description',
        'route_template',
        'query_template',
        'required_params',
        'input_schema',
        'phrases',
        'example_prompts',
        'permission_key',
        'required_role',
        'risk_level',
        'confirmation_required',
        'confidence',
        'evidence_count',
        'evidence',
        'pinecone_matches',
        'reasoning',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'required_params' => 'array',
        'input_schema' => 'array',
        'phrases' => 'array',
        'example_prompts' => 'array',
        'confirmation_required' => 'boolean',
        'confidence' => 'decimal:3',
        'evidence_count' => 'integer',
        'evidence' => 'array',
        'pinecone_matches' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function targetAction(): BelongsTo
    {
        return $this->belongsTo(CopilotAction::class, 'target_copilot_action_id');
    }

    public function createdAction(): BelongsTo
    {
        return $this->belongsTo(CopilotAction::class, 'created_action_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
