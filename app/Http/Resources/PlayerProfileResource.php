<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'nickname' => $this->nickname,
            'display_name' => $this->display_name,
            'photo_url' => $this->photo_url,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->age,
            'gender' => $this->gender?->value,
            'location' => $this->whenLoaded('geographicUnit', fn() => [
                'id' => $this->geographicUnit->id,
                'name' => $this->geographicUnit->name,
                'full_path' => $this->geographicUnit->getFullPath(),
            ]),
            'rating' => [
                'current' => $this->rating,
                'best' => $this->best_rating,
                'category' => $this->rating_category?->value,
                'category_label' => $this->rating_category?->label(),
            ],
            'stats' => [
                'total_matches' => $this->total_matches,
                'wins' => $this->wins,
                'losses' => $this->losses,
                'win_rate' => $this->lifetime_win_rate,
                'frames_won' => $this->lifetime_frames_won,
                'frames_lost' => $this->lifetime_frames_lost,
                'frame_difference' => $this->lifetime_frame_difference,
                'frame_win_rate' => $this->lifetime_frame_win_rate,
                'tournaments_played' => $this->tournaments_played,
                'tournaments_won' => $this->tournaments_won,
            ],
            'age_category' => $this->when($this->getAgeCategory(), fn() => [
                'name' => $this->getAgeCategory()->name,
                'code' => $this->getAgeCategory()->code,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
