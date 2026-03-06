<?php

namespace App\Http\Requests\Api\Signhost;

use App\Http\Requests\Api\ApiRequest;

class SignhostGenerateContractRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $dealId = $this->route('dealId');
        if ($dealId) {
            $this->merge([
                'entity_type' => 'Deal',
                'entity_id' => (int) $dealId,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'max:255'],
            'entity_id' => ['required', 'integer', 'min:1'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
