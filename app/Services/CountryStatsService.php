<?php

namespace App\Services;

use App\Enums\GeographicLevel;
use App\Enums\RatingCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CountryStatsService
{
    /**
     * Cache TTL in seconds (1 hour - country stats don't change frequently)
     */
    protected const CACHE_TTL = 3600;

    /**
     * Number of top players to use for the ranking average
     */
    protected const TOP_PLAYERS_COUNT = 10;

    /**
     * Get country rankings with all stats.
     *
     * Uses an optimized query with window functions to calculate:
     * - Top 10 players average rating (primary ranking metric)
     * - Total players
     * - Pro player count
     * - Overall average rating
     * - Total tournaments won
     * - Total matches played
     *
     * @param string $sortBy Sort field: 'top_10_avg', 'total_players', 'pro_count', 'avg_rating', 'tournaments_won'
     * @return Collection
     */
    public function getCountryRankings(string $sortBy = 'top_10_avg'): Collection
    {
        $cacheKey = "country_rankings:{$sortBy}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sortBy) {
            // Step 1: Get Top 10 Average per country using window function
            $top10Averages = $this->getTop10Averages();

            // Step 2: Get aggregate stats per country
            $countryStats = $this->getCountryAggregateStats();

            // Step 3: Merge the data and calculate final rankings
            $rankings = $this->mergeAndRank($top10Averages, $countryStats, $sortBy);

            return $rankings;
        });
    }

    /**
     * Calculate Top 10 players average rating per country.
     *
     * Uses PostgreSQL window function ROW_NUMBER() to efficiently
     * get top 10 players per country without N+1 queries.
     */
    protected function getTop10Averages(): array
    {
        $topN = self::TOP_PLAYERS_COUNT;

        // Using window function for efficient top-N per group
        $sql = "
            WITH ranked_players AS (
                SELECT
                    country_id,
                    rating,
                    ROW_NUMBER() OVER (
                        PARTITION BY country_id
                        ORDER BY rating DESC
                    ) as rank_in_country
                FROM player_profiles
                WHERE country_id IS NOT NULL
            )
            SELECT
                country_id,
                ROUND(AVG(rating)::numeric, 0) as top_10_avg,
                COUNT(*) as top_players_count
            FROM ranked_players
            WHERE rank_in_country <= ?
            GROUP BY country_id
        ";

        $results = DB::select($sql, [$topN]);

        $averages = [];
        foreach ($results as $row) {
            $averages[$row->country_id] = [
                'top_10_avg' => (int) $row->top_10_avg,
                'top_players_count' => (int) $row->top_players_count,
            ];
        }

        return $averages;
    }

    /**
     * Get aggregate statistics per country.
     *
     * Uses composite indexes for optimal performance:
     * - idx_player_profiles_country_rating
     * - idx_player_profiles_country_category
     * - idx_player_profiles_country_tournaments
     */
    protected function getCountryAggregateStats(): array
    {
        $proCategory = RatingCategory::PRO->value;

        $sql = "
            SELECT
                pp.country_id,
                gu.name as country_name,
                gu.code as country_code,
                COUNT(*) as total_players,
                ROUND(AVG(pp.rating)::numeric, 0) as avg_rating,
                SUM(CASE WHEN pp.rating_category = ? THEN 1 ELSE 0 END) as pro_count,
                SUM(pp.tournaments_won) as tournaments_won,
                SUM(pp.total_matches) as total_matches,
                SUM(pp.wins) as total_wins,
                MAX(pp.rating) as highest_rating
            FROM player_profiles pp
            INNER JOIN geographic_units gu ON gu.id = pp.country_id
            WHERE pp.country_id IS NOT NULL
              AND gu.level = ?
            GROUP BY pp.country_id, gu.name, gu.code
        ";

        $results = DB::select($sql, [$proCategory, GeographicLevel::NATIONAL->value]);

        $stats = [];
        foreach ($results as $row) {
            $stats[$row->country_id] = [
                'country_id' => $row->country_id,
                'country_name' => $row->country_name,
                'country_code' => $row->country_code,
                'total_players' => (int) $row->total_players,
                'avg_rating' => (int) $row->avg_rating,
                'pro_count' => (int) $row->pro_count,
                'tournaments_won' => (int) $row->tournaments_won,
                'total_matches' => (int) $row->total_matches,
                'total_wins' => (int) $row->total_wins,
                'highest_rating' => (int) $row->highest_rating,
            ];
        }

        return $stats;
    }

    /**
     * Merge top 10 averages with aggregate stats and apply ranking.
     */
    protected function mergeAndRank(array $top10Averages, array $countryStats, string $sortBy): Collection
    {
        $merged = [];

        foreach ($countryStats as $countryId => $stats) {
            $top10Data = $top10Averages[$countryId] ?? ['top_10_avg' => 0, 'top_players_count' => 0];

            $merged[] = array_merge($stats, [
                'top_10_avg' => $top10Data['top_10_avg'],
                'top_players_count' => $top10Data['top_players_count'],
            ]);
        }

        // Sort by the specified field
        $sortField = $this->validateSortField($sortBy);

        usort($merged, function ($a, $b) use ($sortField) {
            return $b[$sortField] <=> $a[$sortField];
        });

        // Add rank
        $rank = 1;
        foreach ($merged as &$country) {
            $country['rank'] = $rank++;
        }

        return collect($merged);
    }

    /**
     * Validate and return a safe sort field.
     */
    protected function validateSortField(string $sortBy): string
    {
        $allowedFields = [
            'top_10_avg',
            'total_players',
            'pro_count',
            'avg_rating',
            'tournaments_won',
            'total_matches',
            'highest_rating',
        ];

        return in_array($sortBy, $allowedFields) ? $sortBy : 'top_10_avg';
    }

    /**
     * Clear the country rankings cache.
     */
    public function clearCache(): void
    {
        $sortFields = ['top_10_avg', 'total_players', 'pro_count', 'avg_rating', 'tournaments_won'];

        foreach ($sortFields as $field) {
            Cache::forget("country_rankings:{$field}");
        }
    }

    /**
     * Get a single country's stats.
     */
    public function getCountryStats(int $countryId): ?array
    {
        $rankings = $this->getCountryRankings();

        return $rankings->firstWhere('country_id', $countryId);
    }
}
