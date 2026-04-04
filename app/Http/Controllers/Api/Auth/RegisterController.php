<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\RegisterClientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Services\EmailVerificationCodeService;

class RegisterController extends Controller
{
    public function store(
        RegisterRequest $request,
        RegisterClientAction $action,
        EmailVerificationCodeService $verificationCodes
    )
    {
        $user = $action->execute($request->validated());
        $verificationCodes->issue(
            $user,
            $request->validated('locale'),
            $request->header('Accept-Language')
        );

        return response()->json([
            'verification_required' => true,
            'email' => $user->email,
            'message' => config('mail.default') === 'log'
                ? 'Registration successful. In local development the verification code is written to storage/logs/laravel.log because MAIL_MAILER=log.'
                : 'Registration successful. Continue to verify your email to receive a code.',
        ], 201);
    }
}
