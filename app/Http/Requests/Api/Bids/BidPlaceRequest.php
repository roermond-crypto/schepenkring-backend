<?php

namespace App\Http\Requests\Api\Bids;

use App\Http\Requests\Api\ApiRequest;

class BidPlaceRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
