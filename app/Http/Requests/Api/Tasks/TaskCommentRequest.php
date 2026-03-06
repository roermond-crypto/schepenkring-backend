<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskCommentRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
        ];
    }
}
