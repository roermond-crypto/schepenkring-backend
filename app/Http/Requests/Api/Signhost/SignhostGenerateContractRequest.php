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
            'pdf' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:20480'],
            'send_to_signhost' => ['nullable', 'boolean'],
            'recipients' => ['nullable', 'array', 'min:1'],
            'recipients.*.email' => ['nullable', 'email'],
            'recipients.*.name' => ['nullable', 'string', 'max:255'],
            'recipients.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recipients.*.role' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'otp_code' => ['nullable', 'string', 'max:10'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
