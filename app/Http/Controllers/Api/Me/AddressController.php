<?php

namespace App\Http\Controllers\Api\Me;

use App\Actions\Me\UpdateAddressAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MeAddressRequest;
use App\Http\Resources\UserResource;

class AddressController extends Controller
{
    public function update(MeAddressRequest $request, UpdateAddressAction $action)
    {
        $user = $action->execute($request->user(), $request->validated());

        return response()->json([
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ]);
    }
}
