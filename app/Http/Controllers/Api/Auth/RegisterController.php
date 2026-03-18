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

        // Send the verification email. Email verification is required before
        // the user can log in, so we always dispatch the Registered event.
        event(new Registered($user));

        // Do NOT issue a usable API token here — the user must verify their
        // email address first. The frontend should redirect to the
        // "check your inbox" screen and prompt the user to log in after
        // verifying. Returning the user data (without a token) gives the
        // frontend enough context to show the correct message.
        return response()->json([
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
            'message' => 'Registration successful. Please check your email to verify your account before logging in.',
        ], 201);
    }
}
