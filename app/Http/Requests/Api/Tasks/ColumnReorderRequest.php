<?php

namespace App\Http\Requests\Api\Tasks;

use App\Http\Requests\Api\ApiRequest;

class ColumnReorderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'columns' => ['required', 'array'],
            'columns.*.id' => ['required', 'exists:columns,id'],
            'columns.*.position' => ['required', 'integer'],
        ];
    }
}
