<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskRemindRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'realtime' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
        ];
    }
}
