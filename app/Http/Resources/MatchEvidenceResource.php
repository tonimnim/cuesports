<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'type' => $this->type,
            'url' => $this->url,
            'description' => $this->description,
            'uploaded_by' => [
                'id' => $this->uploadedBy?->id,
                'name' => $this->uploadedBy?->playerProfile?->display_name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
