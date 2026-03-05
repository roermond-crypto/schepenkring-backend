<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class ColumnStoreRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'board_id' => ['required', 'exists:boards,id'],
            'name' => ['required', 'string'],
            'position' => ['required', 'integer'],
        ];
    }
}
