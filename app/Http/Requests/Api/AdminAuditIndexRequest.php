<?php

namespace App\Http\Requests\Api;

use App\Enums\AuditResult;
use App\Enums\RiskLevel;
use Illuminate\Validation\Rule;

class AdminAuditIndexRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'action' => ['nullable'],
            'action.*' => ['string', 'max:255'],
            'entity_type' => ['nullable'],
            'entity_type.*' => ['string', 'max:255'],
            'entity_id' => ['nullable', 'integer'],
            'risk_level' => ['nullable', Rule::in(['LOW', 'MED', ...array_map(fn (RiskLevel $level) => $level->value, RiskLevel::cases())])],
            'result' => ['nullable', Rule::in(array_map(fn (AuditResult $result) => $result->value, AuditResult::cases()))],
            'search' => ['nullable', 'string', 'max:255'],
            'query' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'action', 'risk_level', 'result'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
