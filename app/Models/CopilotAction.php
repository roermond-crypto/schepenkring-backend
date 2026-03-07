<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CopilotAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_id',
        'title',
        'short_description',
        'module',
        'description',
        'route_template',
        'query_template',
        'required_params',
        'input_schema',
        'example_inputs',
        'example_prompts',
        'side_effects',
        'idempotency_rules',
        'rate_limit_class',
        'fresh_auth_required_minutes',
        'tags',
        'permission_key',
        'required_role',
        'risk_level',
        'confirmation_required',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'required_params' => 'array',
        'input_schema' => 'array',
        'example_inputs' => 'array',
        'example_prompts' => 'array',
        'side_effects' => 'array',
        'idempotency_rules' => 'array',
        'tags' => 'array',
        'fresh_auth_required_minutes' => 'integer',
        'confirmation_required' => 'boolean',
        'enabled' => 'boolean',
    ];

    public function phrases(): HasMany
    {
        return $this->hasMany(CopilotActionPhrase::class);
    }
}
