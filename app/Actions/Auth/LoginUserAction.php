<?php

namespace App\Actions\Auth;

use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(array $data): array
    {
        $user = $this->users->findByEmail($data['email']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials.',
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'Account is not active.',
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $tokenName = $data['device_name'] ?? 'api';
        $token = $user->createToken($tokenName);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }
}
