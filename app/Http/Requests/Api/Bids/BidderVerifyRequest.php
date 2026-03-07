<?php

namespace App\Http\Requests\Api\Bids;

use App\Http\Requests\Api\ApiRequest;

class BidderVerifyRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
        ];
    }
}
