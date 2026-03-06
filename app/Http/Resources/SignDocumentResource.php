<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SignDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $url = Storage::disk('public')->exists($this->file_path)
            ? Storage::disk('public')->url($this->file_path)
            : null;

        return [
            'id' => $this->id,
            'sign_request_id' => $this->sign_request_id,
            'type' => $this->type,
            'file_path' => $this->file_path,
            'file_url' => $url,
            'sha256' => $this->sha256,
            'created_at' => $this->created_at,
        ];
    }
}
