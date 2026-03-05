<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskStatusRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:New,Pending,To Do,In Progress,Done'],
        ];
    }
}
