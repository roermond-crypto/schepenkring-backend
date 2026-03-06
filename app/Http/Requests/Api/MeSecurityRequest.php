<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class MeSecurityRequest extends ApiRequest
{
    public function rules(): array
    {
        $enable = $this->has('two_factor_enabled') && $this->boolean('two_factor_enabled');
        $disable = $this->has('two_factor_enabled') && ! $this->boolean('two_factor_enabled');

        return [
            'two_factor_enabled' => ['required', 'boolean'],
            'otp_secret' => [
                Rule::requiredIf($enable),
                'string',
                'min:16',
                'max:64',
            ],
            'otp_code' => [
                Rule::requiredIf($enable),
                'string',
                'max:10',
            ],
            'password' => [
                Rule::requiredIf($disable),
                'string',
            ],
        ];
    }
}
