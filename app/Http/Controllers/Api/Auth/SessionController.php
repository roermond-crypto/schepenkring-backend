<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\LogoutUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function store(LoginRequest $request, LoginUserAction $action)
    {
        $result = $action->execute($request->validated());

        return response()->json([
            'user' => new UserResource($result['user']->load(['locations', 'clientLocation'])),
            'token' => $result['token'],
        ]);
    }

    public function destroy(Request $request, LogoutUserAction $action)
    {
        $action->execute($request->user());

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
