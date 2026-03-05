<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\User\AssignUserLocationsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminUserLocationUpdateRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;

class UserLocationController extends Controller
{
    public function update(AdminUserLocationUpdateRequest $request, int $id, AssignUserLocationsAction $action, UserRepository $users)
    {
        $target = $users->findOrFail($id);

        $user = $action->execute(
            $target,
            $request->validated(),
            $request->user(),
            $request->header('Idempotency-Key')
        );

        return response()->json([
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ]);
    }
}
