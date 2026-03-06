<?php

namespace App\Http\Requests\Api\Leads;

use App\Http\Requests\Api\ApiRequest;
use Illuminate\Validation\Rule;

class ConversationMessageStoreRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'client_message_id' => ['nullable', 'string', 'max:255'],
            'delivery_state' => ['nullable', Rule::in(['queued', 'sent', 'failed'])],
            'visitor_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
