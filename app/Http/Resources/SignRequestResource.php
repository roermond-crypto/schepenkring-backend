<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'provider' => $this->provider,
            'status' => $this->status,
            'signhost_transaction_id' => $this->signhost_transaction_id,
            'sign_url' => $this->sign_url,
            'requested_by_user_id' => $this->requested_by_user_id,
            'metadata' => $this->metadata,
            'documents' => SignDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
