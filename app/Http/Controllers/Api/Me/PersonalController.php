<?php

namespace App\Http\Controllers\Api\Me;

use App\Actions\Me\UpdatePersonalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MePersonalRequest;
use App\Http\Resources\UserResource;

class PersonalController extends Controller
{
    public function update(MePersonalRequest $request, UpdatePersonalAction $action)
    {
        $user = $action->execute(
            $request->user(),
            $request->validated(),
            $request->header('Idempotency-Key')
        );

        return response()->json([
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ]);
    }
}
