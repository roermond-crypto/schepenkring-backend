<?php

namespace App\Http\Middleware;

use App\Security\ActionSecurityRegistry;
use App\Services\ActionAuditService;
use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;

class EnforceActionSecurity
{
    public function __construct(
        private IdempotencyService $idempotency,
        private ActionAuditService $audit
    ) {
    }

    public function handle(Request $request, Closure $next, string $actionKey)
    {
        $definition = ActionSecurityRegistry::get($actionKey);
        if (!$definition) {
            return response()->json([
                'message' => 'Action security policy not configured.',
                'action' => $actionKey,
            ], 500);
        }

        $request->attributes->set('action_key', $actionKey);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $securityCheck = $this->enforceSecurityLevel($request, $definition);
        if ($securityCheck !== null) {
            return $securityCheck;
        }

        $idempotencyRecord = null;
        if (!empty($definition['idempotency'])) {
            $result = $this->idempotency->begin($request, (int) ($definition['idempotency_ttl'] ?? 900));

            if ($result['status'] === 'missing') {
                return response()->json([
                    'message' => 'Idempotency-Key header or idempotency_key is required.',
                ], 400);
            }

            if ($result['status'] === 'conflict') {
                return response()->json([
                    'message' => 'Idempotency-Key reuse with different request.',
                ], 409);
            }

            if ($result['status'] === 'processing') {
                return response()->json([
                    'message' => 'Request already in progress.',
                ], 409);
            }

            if ($result['status'] === 'replay') {
                return $result['response'];
            }

            $idempotencyRecord = $result['record'] ?? null;
        }

        $entity = null;
        $beforeState = null;
        if (!empty($definition['audit']) || !empty($definition['snapshot'])) {
            $entity = $this->resolveEntity($definition, $request);
            if (!empty($definition['snapshot']) && $entity) {
                $beforeState = $entity->toArray();
            }
        }

        $response = $next($request);

        if ($idempotencyRecord) {
            $this->idempotency->storeResponse($idempotencyRecord, $response);
        }

        if (!empty($definition['audit']) && $response->getStatusCode() < 400) {
            $afterState = null;
            $entityAfter = $entity;
            if (!empty($definition['snapshot'])) {
                $entityAfter = $this->resolveEntity($definition, $request);
                $afterState = $entityAfter?->toArray();
            }

            $this->audit->record(
                $actionKey,
                $definition,
                $request,
                $beforeState,
                $afterState,
                $entityAfter
            );
        }

        return $response;
    }

    private function enforceSecurityLevel(Request $request, array $definition): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->isAdmin()) {
            return null;
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $level = (string) ($definition['level'] ?? 'low');
        $config = config('security.levels.' . $level);
        if (!$config) {
            return response()->json([
                'message' => 'Security policy not configured.',
                'security_level' => $level,
            ], 500);
        }

        $requiredStrength = (string) ($config['required_strength'] ?? 'password');
        $currentStrength = (string) ($token->auth_strength ?? 'password');

        if (!$this->meetsRequirement($currentStrength, $requiredStrength)) {
            return response()->json([
                'message' => 'Step-up verification required.',
                'step_up_required' => true,
                'required_level' => $level,
            ], 403);
        }

        $freshMinutes = $definition['fresh'] ?? $config['fresh_minutes'] ?? null;
        if ($freshMinutes !== null) {
            $lastVerified = $token->last_verified_at ?? $token->created_at;
            if (!$lastVerified || $lastVerified->diffInMinutes(now()) > (int) $freshMinutes) {
                return response()->json([
                    'message' => 'Recent verification required.',
                    'step_up_required' => true,
                    'required_level' => $level,
                    'fresh_minutes' => (int) $freshMinutes,
                ], 403);
            }
        }

        return null;
    }

    private function meetsRequirement(string $current, string $required): bool
    {
        $order = [
            'password' => 1,
            'otp' => 2,
            'mfa' => 3,
            'passkey' => 4,
        ];

        $currentRank = $order[$current] ?? 0;
        $requiredRank = $order[$required] ?? 1;

        return $currentRank >= $requiredRank;
    }

    private function resolveEntity(array $definition, Request $request): ?\Illuminate\Database\Eloquent\Model
    {
        $model = $definition['model'] ?? null;
        $routeParam = $definition['route_param'] ?? null;

        if (!$model || !$routeParam) {
            return null;
        }

        $id = $request->route($routeParam);
        if (!$id) {
            return null;
        }

        $query = $model::query();
        $with = $definition['with'] ?? [];
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }
}
