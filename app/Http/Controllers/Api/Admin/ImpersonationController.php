<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Impersonation\StartImpersonationAction;
use App\Actions\Impersonation\StopImpersonationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImpersonationStartRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ImpersonationController extends Controller
{
    public function store(ImpersonationStartRequest $request, int $userId, StartImpersonationAction $action, UserRepository $users)
    {
        $target = $users->findOrFail($userId);

        $result = $action->execute(
            $request->user(),
            $target,
            $request->validated(),
            $request->header('Idempotency-Key')
        );

        return response()->json([
            'token' => $result['token'],
            'session' => [
                'id' => $result['session']->id,
                'started_at' => $result['session']->started_at,
            ],
            'impersonated' => new UserResource($result['impersonated']->load(['locations', 'clientLocation'])),
        ]);
    }

    public function destroy(Request $request, StopImpersonationAction $action)
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token) {
            throw ValidationException::withMessages([
                'token' => 'No active token found.',
            ]);
        }

        $result = $action->execute(
            $request->user(),
            $token->id,
            $request->header('Idempotency-Key')
        );

        return response()->json([
            'token' => $result['token'],
            'impersonator' => new UserResource($result['impersonator']->load(['locations', 'clientLocation'])),
        ]);
    }
}
