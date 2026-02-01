<?php

namespace App\Http\Resources;

use App\Enums\GeographicLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerRankingResource extends JsonResource
{
    /**
     * The rank position to include in the resource.
     */
    public ?int $rank = null;

    /**
     * Set the rank for this resource.
     */
    public function setRank(int $rank): static
    {
        $this->rank = $rank;
        return $this;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $geographicUnit = $this->geographicUnit;
        $country = $this->country;

        // Get community (ATOMIC level - where player registered)
        $community = $geographicUnit?->name;

        // Get county/district (MESO level ancestor)
        $county = $geographicUnit?->getAncestorAtLevel(GeographicLevel::MESO)?->name;

        return [
            'rank' => $this->rank,
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'nickname' => $this->nickname,
            'photo_url' => $this->photo_url,
            'rating' => $this->rating,
            'rating_category' => [
                'value' => $this->rating_category->value,
                'label' => $this->rating_category->label(),
            ],
            'wins' => $this->wins,
            'total_matches' => $this->total_matches,
            'tournaments_won' => $this->tournaments_won,
            'community' => $community,
            'county' => $county,
            'country' => $country ? [
                'id' => $country->id,
                'name' => $country->name,
                'code' => $country->code,
            ] : null,
        ];
    }
}
