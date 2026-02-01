<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryRankingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->resource['rank'],
            'country' => [
                'id' => $this->resource['country_id'],
                'name' => $this->resource['country_name'],
                'code' => $this->resource['country_code'],
            ],
            'top_10_avg' => $this->resource['top_10_avg'],
            'top_players_count' => $this->resource['top_players_count'],
            'total_players' => $this->resource['total_players'],
            'pro_count' => $this->resource['pro_count'],
            'avg_rating' => $this->resource['avg_rating'],
            'tournaments_won' => $this->resource['tournaments_won'],
            'total_matches' => $this->resource['total_matches'],
            'total_wins' => $this->resource['total_wins'],
            'highest_rating' => $this->resource['highest_rating'],
        ];
    }
}
