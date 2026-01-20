<?php

namespace App\Services;

use App\Models\Match;
use App\Models\PlayerProfile;
use App\Models\PlayerRatingHistory;
use App\Models\TournamentParticipant;

class RatingService
{
    // ELO K-factor constants
    protected const K_FACTOR_NEW = 40;      // For players with < 30 matches
    protected const K_FACTOR_NORMAL = 24;   // For regular players
    protected const K_FACTOR_HIGH = 16;     // For players rated >= 2000

    protected const NEW_PLAYER_THRESHOLD = 30;
    protected const HIGH_RATING_THRESHOLD = 2000;

    /**
     * Calculate and apply rating changes after a match.
     */
    public function processMatchResult(Match $match): void
    {
        if (!$match->isCompleted() || $match->isBye()) {
            return;
        }

        $winnerParticipant = $match->winner;
        $loserParticipant = $match->loser;

        if (!$winnerParticipant || !$loserParticipant) {
            return;
        }

        $winnerProfile = $winnerParticipant->playerProfile;
        $loserProfile = $loserParticipant->playerProfile;

        $winnerOldRating = $winnerProfile->rating;
        $loserOldRating = $loserProfile->rating;

        // Calculate new ratings
        $ratingChanges = $this->calculateRatingChange(
            $winnerProfile,
            $loserProfile,
            $match->player1_score,
            $match->player2_score,
            $match->winner_id === $match->player1_id
        );

        // Apply rating changes
        $winnerProfile->updateRating(
            $ratingChanges['winner_new_rating'],
            PlayerRatingHistory::REASON_MATCH_RESULT,
            $match
        );

        $loserProfile->updateRating(
            $ratingChanges['loser_new_rating'],
            PlayerRatingHistory::REASON_MATCH_RESULT,
            $match
        );

        // Update match history records with correct rating info
        $this->updateMatchHistoryRatings(
            $match,
            $winnerParticipant,
            $winnerOldRating,
            $ratingChanges['winner_new_rating'],
            $loserParticipant,
            $loserOldRating,
            $ratingChanges['loser_new_rating']
        );
    }

    /**
     * Calculate rating changes using ELO algorithm.
     *
     * @param PlayerProfile $winner
     * @param PlayerProfile $loser
     * @param int $winnerFrames
     * @param int $loserFrames
     * @param bool $winnerIsPlayer1
     * @return array{winner_change: int, loser_change: int, winner_new_rating: int, loser_new_rating: int}
     */
    public function calculateRatingChange(
        PlayerProfile $winner,
        PlayerProfile $loser,
        int $player1Score,
        int $player2Score,
        bool $winnerIsPlayer1
    ): array {
        $winnerRating = $winner->rating;
        $loserRating = $loser->rating;

        // Calculate expected scores
        $winnerExpected = $this->expectedScore($winnerRating, $loserRating);
        $loserExpected = 1 - $winnerExpected;

        // Get K-factors
        $winnerK = $this->getKFactor($winner);
        $loserK = $this->getKFactor($loser);

        // Calculate margin of victory multiplier (bonus for decisive wins)
        $marginMultiplier = $this->calculateMarginMultiplier(
            $winnerIsPlayer1 ? $player1Score : $player2Score,
            $winnerIsPlayer1 ? $player2Score : $player1Score
        );

        // Calculate rating changes
        // Winner: actual score = 1 (win)
        // Loser: actual score = 0 (loss)
        $winnerChange = (int) round($winnerK * $marginMultiplier * (1 - $winnerExpected));
        $loserChange = (int) round($loserK * $marginMultiplier * (0 - $loserExpected));

        // Ensure minimum change of 1 for meaningful matches
        if ($winnerChange === 0) {
            $winnerChange = 1;
        }
        if ($loserChange === 0) {
            $loserChange = -1;
        }

        $winnerNewRating = max(0, $winnerRating + $winnerChange);
        $loserNewRating = max(0, $loserRating + $loserChange);

        return [
            'winner_change' => $winnerChange,
            'loser_change' => $loserChange,
            'winner_new_rating' => $winnerNewRating,
            'loser_new_rating' => $loserNewRating,
        ];
    }

    /**
     * Calculate expected score using ELO formula.
     * E = 1 / (1 + 10^((Rb - Ra) / 400))
     */
    protected function expectedScore(int $ratingA, int $ratingB): float
    {
        return 1 / (1 + pow(10, ($ratingB - $ratingA) / 400));
    }

    /**
     * Get the K-factor for a player based on their experience and rating.
     */
    protected function getKFactor(PlayerProfile $player): int
    {
        // New players have higher K-factor for faster calibration
        if ($player->total_matches < self::NEW_PLAYER_THRESHOLD) {
            return self::K_FACTOR_NEW;
        }

        // High-rated players have lower K-factor for stability
        if ($player->rating >= self::HIGH_RATING_THRESHOLD) {
            return self::K_FACTOR_HIGH;
        }

        return self::K_FACTOR_NORMAL;
    }

    /**
     * Calculate margin of victory multiplier.
     * Winning 2-0 is worth more than 2-1.
     */
    protected function calculateMarginMultiplier(int $winnerFrames, int $loserFrames): float
    {
        // For best of 3: 2-0 is decisive, 2-1 is close
        if ($loserFrames === 0) {
            return 1.2; // 20% bonus for clean sweep
        }

        return 1.0; // Normal for close games
    }

    /**
     * Update the match history records with the correct rating changes.
     */
    protected function updateMatchHistoryRatings(
        Match $match,
        TournamentParticipant $winner,
        int $winnerOldRating,
        int $winnerNewRating,
        TournamentParticipant $loser,
        int $loserOldRating,
        int $loserNewRating
    ): void {
        // Update winner's match history
        $match->matchHistoryForParticipant($winner)?->update([
            'rating_before' => $winnerOldRating,
            'rating_after' => $winnerNewRating,
            'rating_change' => $winnerNewRating - $winnerOldRating,
        ]);

        // Update loser's match history
        $match->matchHistoryForParticipant($loser)?->update([
            'rating_before' => $loserOldRating,
            'rating_after' => $loserNewRating,
            'rating_change' => $loserNewRating - $loserOldRating,
        ]);
    }

    /**
     * Preview rating changes without applying them.
     */
    public function previewRatingChange(
        PlayerProfile $player1,
        PlayerProfile $player2,
        int $player1Score,
        int $player2Score
    ): array {
        $player1Wins = $player1Score > $player2Score;
        $winner = $player1Wins ? $player1 : $player2;
        $loser = $player1Wins ? $player2 : $player1;

        $changes = $this->calculateRatingChange(
            $winner,
            $loser,
            $player1Score,
            $player2Score,
            $player1Wins
        );

        return [
            'player1' => [
                'current_rating' => $player1->rating,
                'new_rating' => $player1Wins ? $changes['winner_new_rating'] : $changes['loser_new_rating'],
                'change' => $player1Wins ? $changes['winner_change'] : $changes['loser_change'],
            ],
            'player2' => [
                'current_rating' => $player2->rating,
                'new_rating' => $player1Wins ? $changes['loser_new_rating'] : $changes['winner_new_rating'],
                'change' => $player1Wins ? $changes['loser_change'] : $changes['winner_change'],
            ],
        ];
    }

    /**
     * Apply a manual rating adjustment (admin action).
     */
    public function adjustRating(
        PlayerProfile $player,
        int $adjustment,
        string $reason = null
    ): void {
        $newRating = max(0, $player->rating + $adjustment);
        $player->updateRating(
            $newRating,
            PlayerRatingHistory::REASON_ADMIN_ADJUSTMENT
        );
    }

    /**
     * Apply tournament bonus to top performers.
     */
    public function applyTournamentBonus(
        TournamentParticipant $participant,
        int $bonus
    ): void {
        $profile = $participant->playerProfile;
        $newRating = max(0, $profile->rating + $bonus);
        $profile->updateRating(
            $newRating,
            PlayerRatingHistory::REASON_TOURNAMENT_BONUS
        );
    }

    /**
     * Apply rating decay for inactive players.
     * Called by a scheduled job.
     */
    public function applyInactivityDecay(
        PlayerProfile $player,
        int $decayAmount
    ): void {
        $newRating = max(0, $player->rating - $decayAmount);
        $player->updateRating(
            $newRating,
            PlayerRatingHistory::REASON_DECAY
        );
    }
}
