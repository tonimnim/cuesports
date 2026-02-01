<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryRankingResource;
use App\Http\Resources\PlayerRankingCollection;
use App\Services\CountryStatsService;
use App\Services\PlayerStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function __construct(
        private PlayerStatsService $playerStatsService,
        private CountryStatsService $countryStatsService
    ) {}

    /**
     * Get player rankings with filters.
     *
     * GET /api/players/rankings
     *
     * Query params:
     * - country_id: Filter by country
     * - region_id: Filter by region (cascading filter)
     * - category: Filter by rating category (beginner/intermediate/advanced/pro)
     * - sort_by: Sort metric (rating, wins, tournaments_won, total_matches)
     * - page, per_page: Pagination
     */
    public function rankings(Request $request): PlayerRankingCollection
    {
        $validated = $request->validate([
            'country_id' => ['nullable', 'integer', 'exists:geographic_units,id'],
            'region_id' => ['nullable', 'integer', 'exists:geographic_units,id'],
            'category' => ['nullable', 'string', 'in:beginner,intermediate,advanced,pro'],
            'sort_by' => ['nullable', 'string', 'in:rating,wins,tournaments_won,total_matches'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $players = $this->playerStatsService->getPaginatedRankings(
            countryId: $validated['country_id'] ?? null,
            regionId: $validated['region_id'] ?? null,
            category: $validated['category'] ?? null,
            sortBy: $validated['sort_by'] ?? 'rating',
            perPage: $validated['per_page'] ?? 25
        );

        return new PlayerRankingCollection($players);
    }

    /**
     * Get country rankings.
     *
     * GET /api/countries/rankings
     *
     * Query params:
     * - sort_by: Sort metric (top_10_avg, total_players, pro_count, avg_rating, tournaments_won)
     *
     * Ranking is calculated using Top 10 players average rating per country.
     */
    public function countryRankings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sort_by' => ['nullable', 'string', 'in:top_10_avg,total_players,pro_count,avg_rating,tournaments_won,total_matches,highest_rating'],
        ]);

        $sortBy = $validated['sort_by'] ?? 'top_10_avg';

        $rankings = $this->countryStatsService->getCountryRankings($sortBy);

        return response()->json([
            'data' => CountryRankingResource::collection($rankings),
            'meta' => [
                'total' => $rankings->count(),
                'sort_by' => $sortBy,
                'ranking_method' => 'top_10_players_average',
            ],
        ]);
    }
}
