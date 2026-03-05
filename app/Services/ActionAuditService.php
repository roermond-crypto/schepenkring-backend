<?php

namespace App\Services;

use App\Models\ActionAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActionAuditService
{
    public function __construct(
        private DeviceInfoService $deviceInfo,
        private IdempotencyService $idempotency
    ) {
    }

    public function record(
        string $actionKey,
        array $definition,
        Request $request,
        ?array $oldState,
        ?array $newState,
        ?Model $entity = null
    ): ActionAudit {
        $user = $request->user();
        $location = $this->deviceInfo->resolveLocation($request);
        $deviceId = $request->header('X-Device-Id') ?? $request->input('device_id');

        return ActionAudit::create([
            'action_key' => $actionKey,
            'risk_level' => (string) ($definition['level'] ?? 'low'),
            'user_id' => $user?->id,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'device_id' => $deviceId ?: null,
            'ip_address' => $request->ip(),
            'ip_country' => $location['country'] ?? null,
            'user_agent' => $request->userAgent(),
            'request_id' => $request->attributes->get('request_id'),
            'request_method' => $request->method(),
            'request_path' => '/' . ltrim($request->path(), '/'),
            'request_hash' => $this->idempotency->requestHash($request),
            'old_state' => $oldState,
            'new_state' => $newState,
            'metadata' => [
                'action' => $actionKey,
            ],
        ]);
    }
}
