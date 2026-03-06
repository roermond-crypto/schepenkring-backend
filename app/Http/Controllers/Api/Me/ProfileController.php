<?php

namespace App\Http\Controllers\Api\Me;

use App\Actions\Me\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MeProfileRequest;
use App\Http\Resources\UserResource;

class ProfileController extends Controller
{
    public function update(MeProfileRequest $request, UpdateProfileAction $action)
    {
        $user = $action->execute($request->user(), $request->validated());

        return response()->json([
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ]);
    }
}
