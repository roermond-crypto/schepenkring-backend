<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoatCheckRequest extends FormRequest
{
public function authorize()
{
    return true;
}
    public function rules()
    {
        return [
            'question_text' => 'required|string',
            'type' => 'required|in:YES_NO,MULTI,TEXT,DATE',
            'options' => 'nullable|array',
            'options.*' => 'string',
            'required' => 'sometimes|boolean',
            'ai_prompt' => 'nullable|string',
            'evidence_sources' => 'sometimes|array',
            'evidence_sources.*' => 'in:photos,spec_json,documents',
            'weight' => 'sometimes|in:low,medium,high',
            'boat_type_ids' => 'sometimes|array',
            'boat_type_ids.*' => 'exists:boat_types,id',
        ];
    }
}