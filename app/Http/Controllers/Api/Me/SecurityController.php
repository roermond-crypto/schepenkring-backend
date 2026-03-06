<?php

namespace App\Http\Controllers\Api\Me;

use App\Actions\Me\UpdateSecurityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MeSecurityRequest;
use App\Http\Resources\UserResource;

class SecurityController extends Controller
{
    public function update(MeSecurityRequest $request, UpdateSecurityAction $action)
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
