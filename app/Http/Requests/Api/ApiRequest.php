<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
