<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'country' => $this->whenLoaded('country', fn() => [
                'id' => $this->country->id,
                'name' => $this->country->name,
                'code' => $this->country->code,
            ]),
            'is_verified' => $this->isVerified(),
            'is_active' => $this->is_active,
            'roles' => [
                'is_super_admin' => $this->is_super_admin,
                'is_support' => $this->is_support,
                'is_player' => $this->is_player,
                'is_organizer' => $this->is_organizer,
            ],
            'player_profile' => $this->when(
                $this->is_player && $this->relationLoaded('playerProfile') && $this->playerProfile,
                fn() => new PlayerProfileResource($this->playerProfile)
            ),
            'organizer_profile' => $this->when(
                $this->is_organizer && $this->relationLoaded('organizerProfile') && $this->organizerProfile,
                fn() => new OrganizerProfileResource($this->organizerProfile)
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
