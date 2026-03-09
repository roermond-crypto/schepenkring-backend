<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DisableUserAction;
use App\Actions\User\ListUsersAction;
use App\Actions\User\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminUserIndexRequest;
use App\Http\Requests\Api\AdminUserStoreRequest;
use App\Http\Requests\Api\AdminUserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;

class UserController extends Controller
{
    public function index(AdminUserIndexRequest $request, ListUsersAction $action)
    {
        $users = $action->execute($request->user(), $request->validated());

        return UserResource::collection($users);
    }

    public function store(AdminUserStoreRequest $request, CreateUserAction $action)
    {
        $user = $action->execute(
            $request->validated(),
            $request->user(),
            $request->header('Idempotency-Key')
        );

        return response()->json([
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ], 201);
    }

    public function show(int $id, UserRepository $users)
    {
        $user = $users->queryForActor(request()->user())->findOrFail($id);

        return response()->json([
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ]);
    }

    public function update(AdminUserUpdateRequest $request, int $id, UpdateUserAction $action, UserRepository $users)
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

    public function destroy(int $id, DisableUserAction $action, UserRepository $users)
    {
        $target = $users->findOrFail($id);
        $user = $action->execute(
            $target,
            request()->user(),
            request()->header('Idempotency-Key')
        );

        return response()->json([
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ]);
    }
}
