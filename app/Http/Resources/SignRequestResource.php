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
        $isYacht = $this->entity_type === 'Yacht';

        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'yacht_id' => $isYacht ? $this->entity_id : null,
            'yacht' => $isYacht ? $this->whenLoaded('yacht', fn () => [
                'id' => $this->yacht->id,
                'name' => $this->yacht->name,
                'type' => $this->yacht->type,
                'year' => $this->yacht->year,
                'asking_price' => $this->yacht->asking_price,
            ]) : null,
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
