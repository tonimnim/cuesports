<?php

namespace App\Services;

use App\Enums\GeographicLevel;
use App\Enums\RatingCategory;
use App\Enums\TournamentType;
use App\Models\GeographicUnit;
use App\Models\PlayerMatchHistory;
use App\Models\PlayerProfile;
use App\Models\PlayerRatingHistory;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlayerStatsService
{
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Get player's overall stats summary.
     */
    public function getPlayerStats(PlayerProfile $player): array
    {
        return [
            'rating' => $player->rating,
            'rating_category' => $player->rating_category->label(),
            'best_rating' => $player->best_rating,
            'total_matches' => $player->total_matches,
            'wins' => $player->wins,
            'losses' => $player->losses,
            'win_rate' => $player->lifetime_win_rate,
            'lifetime_frames_won' => $player->lifetime_frames_won,
            'lifetime_frames_lost' => $player->lifetime_frames_lost,
            'lifetime_frame_difference' => $player->lifetime_frame_difference,
            'frame_win_rate' => $player->lifetime_frame_win_rate,
            'tournaments_played' => $player->tournaments_played,
            'tournaments_won' => $player->tournaments_won,
            'tournament_win_rate' => $player->tournament_win_rate,
        ];
    }

    /**
     * Get player's recent match history.
     */
    public function getRecentMatches(
        PlayerProfile $player,
        int $limit = 10
    ): Collection {
        return $player->matchHistory()
            ->with(['tournament', 'opponentProfile'])
            ->orderBy('played_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get player's head-to-head record against a specific opponent.
     */
    public function getHeadToHead(
        PlayerProfile $player,
        PlayerProfile $opponent
    ): array {
        $matches = $player->matchHistory()
            ->where('opponent_profile_id', $opponent->id)
            ->excludingByes()
            ->get();

        $wins = $matches->where('won', true)->count();
        $losses = $matches->where('won', false)->count();
        $framesWon = $matches->sum('frames_won');
        $framesLost = $matches->sum('frames_lost');

        return [
            'total_matches' => $matches->count(),
            'wins' => $wins,
            'losses' => $losses,
            'frames_won' => $framesWon,
            'frames_lost' => $framesLost,
            'frame_difference' => $framesWon - $framesLost,
            'last_match' => $matches->sortByDesc('played_at')->first(),
        ];
    }

    /**
     * Get player's rating history over time.
     */
    public function getRatingHistory(
        PlayerProfile $player,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $query = $player->ratingHistory()
            ->orderBy('created_at', 'asc');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Get player's performance by tournament type.
     */
    public function getPerformanceByTournamentType(PlayerProfile $player): array
    {
        $matches = $player->matchHistory()->excludingByes()->get();

        $result = [];
        foreach (TournamentType::cases() as $type) {
            $typeMatches = $matches->where('tournament_type', $type->value);
            $result[$type->value] = [
                'label' => $type->label(),
                'matches' => $typeMatches->count(),
                'wins' => $typeMatches->where('won', true)->count(),
                'losses' => $typeMatches->where('won', false)->count(),
                'frames_won' => $typeMatches->sum('frames_won'),
                'frames_lost' => $typeMatches->sum('frames_lost'),
            ];
        }

        return $result;
    }

    /**
     * Get leaderboard for a geographic region.
     */
    public function getLeaderboard(
        GeographicUnit $geographicUnit,
        int $limit = 50,
        int $offset = 0,
        string $sortBy = 'rating'
    ): Collection {
        $cacheKey = "leaderboard:{$geographicUnit->id}:{$sortBy}:{$limit}:{$offset}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($geographicUnit, $limit, $offset, $sortBy) {
            // Get all player IDs in this geographic unit and its descendants
            $playerQuery = $this->getPlayersInGeographicScope($geographicUnit);

            return $playerQuery
                ->orderByDesc($sortBy)
                ->when($sortBy === 'rating', function ($q) {
                    // Secondary sort by total matches for players with same rating
                    $q->orderByDesc('total_matches');
                })
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($player, $index) use ($offset) {
                    return [
                        'rank' => $offset + $index + 1,
                        'player' => $player,
                    ];
                });
        });
    }

    /**
     * Get player's rank in a geographic scope.
     */
    public function getPlayerRank(
        PlayerProfile $player,
        GeographicUnit $geographicUnit,
        string $sortBy = 'rating'
    ): int {
        $playersAbove = $this->getPlayersInGeographicScope($geographicUnit)
            ->where($sortBy, '>', $player->{$sortBy})
            ->count();

        return $playersAbove + 1;
    }

    /**
     * Get top players by rating category.
     */
    public function getTopPlayersByCategory(
        RatingCategory $category,
        ?GeographicUnit $geographicUnit = null,
        int $limit = 10
    ): Collection {
        $query = PlayerProfile::where('rating_category', $category->value);

        if ($geographicUnit) {
            $query = $this->scopeToGeographicUnit($query, $geographicUnit);
        }

        return $query
            ->orderByDesc('rating')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics summary for a geographic region.
     */
    public function getRegionStats(GeographicUnit $geographicUnit): array
    {
        $cacheKey = "region_stats:{$geographicUnit->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($geographicUnit) {
            $players = $this->getPlayersInGeographicScope($geographicUnit);

            return [
                'total_players' => $players->count(),
                'average_rating' => (int) round($players->avg('rating') ?? 0),
                'highest_rating' => $players->max('rating') ?? 0,
                'total_matches' => $players->sum('total_matches'),
                'total_tournaments' => $players->sum('tournaments_played'),
                'players_by_category' => [
                    RatingCategory::BEGINNER->value => $players->clone()->where('rating_category', RatingCategory::BEGINNER->value)->count(),
                    RatingCategory::INTERMEDIATE->value => $players->clone()->where('rating_category', RatingCategory::INTERMEDIATE->value)->count(),
                    RatingCategory::ADVANCED->value => $players->clone()->where('rating_category', RatingCategory::ADVANCED->value)->count(),
                    RatingCategory::PRO->value => $players->clone()->where('rating_category', RatingCategory::PRO->value)->count(),
                ],
            ];
        });
    }

    /**
     * Get a player's streak information.
     */
    public function getStreakInfo(PlayerProfile $player): array
    {
        $recentMatches = $player->matchHistory()
            ->excludingByes()
            ->orderBy('played_at', 'desc')
            ->limit(100)
            ->get();

        // Current streak
        $currentStreak = 0;
        $currentStreakType = null;

        foreach ($recentMatches as $match) {
            if ($currentStreakType === null) {
                $currentStreakType = $match->won ? 'win' : 'loss';
            }

            if (($match->won && $currentStreakType === 'win') ||
                (!$match->won && $currentStreakType === 'loss')) {
                $currentStreak++;
            } else {
                break;
            }
        }

        // Best win streak
        $bestWinStreak = $this->calculateBestStreak($recentMatches, true);
        $bestLossStreak = $this->calculateBestStreak($recentMatches, false);

        return [
            'current_streak' => $currentStreak,
            'current_streak_type' => $currentStreakType,
            'best_win_streak' => $bestWinStreak,
            'worst_loss_streak' => $bestLossStreak,
        ];
    }

    /**
     * Calculate the best streak from matches.
     */
    protected function calculateBestStreak(Collection $matches, bool $wins): int
    {
        $bestStreak = 0;
        $currentStreak = 0;

        foreach ($matches->sortBy('played_at') as $match) {
            if ($match->won === $wins) {
                $currentStreak++;
                $bestStreak = max($bestStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return $bestStreak;
    }

    /**
     * Get players in a geographic scope (including descendants).
     */
    protected function getPlayersInGeographicScope(GeographicUnit $unit): Builder
    {
        // Get all descendant unit IDs
        $unitIds = $this->getDescendantUnitIds($unit);

        return PlayerProfile::whereIn('geographic_unit_id', $unitIds);
    }

    /**
     * Scope a query to a geographic unit and its descendants.
     */
    protected function scopeToGeographicUnit(Builder $query, GeographicUnit $unit): Builder
    {
        $unitIds = $this->getDescendantUnitIds($unit);
        return $query->whereIn('geographic_unit_id', $unitIds);
    }

    /**
     * Get all descendant unit IDs for a geographic unit.
     */
    protected function getDescendantUnitIds(GeographicUnit $unit): array
    {
        $cacheKey = "geo_descendants:{$unit->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL * 24, function () use ($unit) {
            return array_merge(
                [$unit->id],
                $unit->descendants()->pluck('id')->toArray()
            );
        });
    }

    /**
     * Clear leaderboard caches for a geographic unit and its ancestors.
     */
    public function clearLeaderboardCache(GeographicUnit $unit): void
    {
        // Clear this unit's cache
        $patterns = [
            "leaderboard:{$unit->id}:*",
            "region_stats:{$unit->id}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        // Clear ancestor caches
        foreach ($unit->getAncestors() as $ancestor) {
            Cache::forget("leaderboard:{$ancestor->id}:*");
            Cache::forget("region_stats:{$ancestor->id}");
        }
    }

    /**
     * Recalculate lifetime stats from match history.
     * Useful for data integrity checks or recovery.
     */
    public function recalculateLifetimeStats(PlayerProfile $player): void
    {
        $stats = $player->matchHistory()
            ->excludingByes()
            ->selectRaw('
                COUNT(*) as total_matches,
                SUM(CASE WHEN won = true THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN won = false THEN 1 ELSE 0 END) as losses,
                SUM(frames_won) as frames_won,
                SUM(frames_lost) as frames_lost
            ')
            ->first();

        $tournamentStats = $player->tournamentParticipations()
            ->selectRaw('
                COUNT(*) as tournaments_played,
                SUM(CASE WHEN final_position = 1 THEN 1 ELSE 0 END) as tournaments_won
            ')
            ->first();

        $player->update([
            'total_matches' => $stats->total_matches ?? 0,
            'wins' => $stats->wins ?? 0,
            'losses' => $stats->losses ?? 0,
            'lifetime_frames_won' => $stats->frames_won ?? 0,
            'lifetime_frames_lost' => $stats->frames_lost ?? 0,
            'lifetime_frame_difference' => ($stats->frames_won ?? 0) - ($stats->frames_lost ?? 0),
            'tournaments_played' => $tournamentStats->tournaments_played ?? 0,
            'tournaments_won' => $tournamentStats->tournaments_won ?? 0,
        ]);
    }

    /**
     * Get paginated player rankings with filters.
     */
    public function getPaginatedRankings(
        ?int $countryId = null,
        ?int $regionId = null,
        ?string $category = null,
        string $sortBy = 'rating',
        int $perPage = 25
    ): LengthAwarePaginator {
        $query = PlayerProfile::query()
            ->with(['geographicUnit', 'country']);

        // Filter by country
        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        // Filter by region (cascading - includes all descendants)
        if ($regionId) {
            $region = GeographicUnit::find($regionId);
            if ($region) {
                $unitIds = $this->getDescendantUnitIds($region);
                $query->whereIn('geographic_unit_id', $unitIds);
            }
        }

        // Filter by rating category
        if ($category) {
            $categoryEnum = RatingCategory::tryFrom($category);
            if ($categoryEnum) {
                $query->where('rating_category', $categoryEnum->value);
            }
        }

        // Validate and apply sorting
        $allowedSortFields = ['rating', 'wins', 'tournaments_won', 'total_matches'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'rating';
        }

        $query->orderByDesc($sortBy);

        // Secondary sort for consistency
        if ($sortBy === 'rating') {
            $query->orderByDesc('total_matches');
        } else {
            $query->orderByDesc('rating');
        }

        // Add ID as final tiebreaker for consistent ordering
        $query->orderBy('id');

        return $query->paginate($perPage);
    }
}
