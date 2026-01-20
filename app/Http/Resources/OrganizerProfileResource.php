<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizerProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_name' => $this->organization_name,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'contact_email' => $this->getContactEmail(),
            'contact_phone' => $this->getContactPhone(),
            'is_active' => $this->is_active,
            'tournaments_hosted' => $this->tournaments_hosted,
            'has_api_access' => !is_null($this->api_key),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
