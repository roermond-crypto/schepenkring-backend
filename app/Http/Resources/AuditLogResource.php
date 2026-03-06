<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $location = $this->whenLoaded('location', function () {
            return $this->location ? [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'code' => $this->location->code,
            ] : null;
        });

        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'actor_id' => $this->actor_id,
            'impersonator_id' => $this->impersonator_id,
            'actor' => $this->whenLoaded('actor', fn () => new AuditUserResource($this->actor)),
            'impersonator' => $this->whenLoaded('impersonator', fn () => new AuditUserResource($this->impersonator)),
            'location_id' => $this->location_id,
            'location' => $location,
            'action' => $this->action,
            'entity_type' => $this->entity_type ?? $this->target_type,
            'entity_id' => $this->entity_id ?? $this->target_id,
            'risk_level' => $this->risk_level,
            'result' => $this->result,
            'ip_address' => $this->ip_address,
            'ip_hash' => $this->ip_hash,
            'user_agent' => $this->user_agent,
            'device_id' => $this->device_id,
            'request_id' => $this->request_id,
            'idempotency_key' => $this->idempotency_key,
            'metadata' => $this->meta,
            'snapshot_before' => $this->snapshot_before,
            'snapshot_after' => $this->snapshot_after,
            'updated_at' => $this->updated_at,
        ];
    }
}
