<?php

namespace App\Http\Controllers\Api\Me;

use App\Actions\Me\UpdatePasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MePasswordRequest;
use App\Http\Resources\UserResource;

class PasswordController extends Controller
{
    public function update(MePasswordRequest $request, UpdatePasswordAction $action)
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
