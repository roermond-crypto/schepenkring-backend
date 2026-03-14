<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiDailyInsight extends Model
{
    protected $fillable = [
        'period_start',
        'period_end',
        'product',
        'environment',
        'timezone',
        'status',
        'overall_status',
        'headline',
        'model',
        'openai_response_id',
        'failure_message',
        'summary_json',
        'top_findings_json',
        'performance_issues_json',
        'security_signals_json',
        'priority_actions_json',
        'raw_input_json',
        'raw_output_json',
        'usage_json',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'summary_json' => 'array',
        'top_findings_json' => 'array',
        'performance_issues_json' => 'array',
        'security_signals_json' => 'array',
        'priority_actions_json' => 'array',
        'raw_input_json' => 'array',
        'raw_output_json' => 'array',
        'usage_json' => 'array',
    ];
}
