<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class TaskReorderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'tasks' => ['required', 'array'],
            'tasks.*.id' => ['required', 'exists:tasks,id'],
            'tasks.*.position' => ['required', 'integer'],
            'tasks.*.column_id' => ['required', 'exists:columns,id'],
        ];
    }
}
