<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\RegisterClientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Auth\Events\Registered;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request, RegisterClientAction $action)
    {
        $user = $action->execute($request->validated());
        event(new Registered($user));
        $token = $user->createToken('register');

        return response()->json([
            'data' => new UserResource($user->load(['clientLocation'])),
            'token' => $token->plainTextToken,
        ], 201);
    }
}
