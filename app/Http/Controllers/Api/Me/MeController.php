<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\ImpersonationContext;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(private ImpersonationContext $context)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user()->load(['locations', 'clientLocation']);
        $impersonator = $this->context->impersonator();

        return response()->json([
            'data' => new UserResource($user),
            'impersonation' => $impersonator ? [
                'session_id' => $this->context->sessionId(),
                'impersonator' => new UserResource($impersonator),
            ] : null,
        ]);
    }
}
