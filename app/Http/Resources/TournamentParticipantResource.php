<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'player' => $this->whenLoaded('playerProfile', fn() => [
                'id' => $this->playerProfile->id,
                'name' => $this->playerProfile->display_name,
                'full_name' => $this->playerProfile->full_name,
                'photo_url' => $this->playerProfile->photo_url,
                'rating' => $this->playerProfile->rating,
                'rating_category' => $this->playerProfile->rating_category->value,
                'location' => $this->playerProfile->geographicUnit ? [
                    'id' => $this->playerProfile->geographicUnit->id,
                    'name' => $this->playerProfile->geographicUnit->name,
                    'full_path' => $this->playerProfile->geographicUnit->getFullPath(),
                ] : null,
            ]),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'can_play' => $this->status->canPlay(),
            ],
            'seed' => $this->seed,
            'group_number' => $this->group_number,
            'group_position' => $this->group_position,
            'stats' => [
                'matches_played' => $this->matches_played,
                'matches_won' => $this->matches_won,
                'matches_lost' => $this->matches_lost,
                'frames_won' => $this->frames_won,
                'frames_lost' => $this->frames_lost,
                'frame_difference' => $this->frame_difference,
                'points' => $this->points,
                'win_rate' => $this->getWinRate(),
            ],
            'final_position' => $this->final_position,
            'registered_at' => $this->registered_at?->toISOString(),
            'eliminated_at' => $this->eliminated_at?->toISOString(),
        ];
    }
}
