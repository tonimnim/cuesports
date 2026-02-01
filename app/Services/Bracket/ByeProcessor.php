<?php

namespace App\Services\Bracket;

use App\Enums\MatchStatus;
use App\Enums\MatchType;
use App\Models\GameMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Handles BYE match processing.
 *
 * A BYE match is when a player has no opponent in a round.
 * The player automatically advances to the next round.
 */
class ByeProcessor
{
    public function __construct(
        protected BracketStructureBuilder $structureBuilder
    ) {}

    /**
     * Process all BYE matches in a collection.
     *
     * BYE matches are matches where exactly one player is assigned.
     * The player with no opponent wins automatically.
     *
     * @param Collection<int, GameMatch> $matches
     * @return int Number of BYE matches processed
     */
    public function processAll(Collection $matches): int
    {
        $processed = 0;

        Log::info("=== BYE PROCESSING START ===", [
            'total_matches_to_check' => $matches->count(),
        ]);

        foreach ($matches as $match) {
            $isBye = $this->isByeMatch($match);
            Log::info("Checking match for BYE", [
                'match_id' => $match->id,
                'round' => $match->round_number,
                'position' => $match->bracket_position,
                'p1' => $match->player1_id,
                'p2' => $match->player2_id,
                'is_bye' => $isBye,
            ]);

            if ($isBye) {
                $this->processBye($match);
                $processed++;
            }
        }

        Log::info("=== BYE PROCESSING COMPLETE ===", [
            'byes_processed' => $processed,
        ]);

        return $processed;
    }

    /**
     * Check if a match is a BYE (exactly one player assigned).
     */
    public function isByeMatch(GameMatch $match): bool
    {
        $hasPlayer1 = $match->player1_id !== null;
        $hasPlayer2 = $match->player2_id !== null;

        // BYE = exactly one player (XOR)
        return $hasPlayer1 xor $hasPlayer2;
    }

    /**
     * Process a single BYE match.
     *
     * - Marks the match as completed with BYE type
     * - Advances the player to the next match
     */
    public function processBye(GameMatch $match): void
    {
        if (!$this->isByeMatch($match)) {
            return;
        }

        // Determine which player has the BYE (the one who IS present)
        $winnerId = $match->player1_id ?? $match->player2_id;

        if (!$winnerId) {
            Log::warning("BYE match {$match->id} has no players - skipping");
            return;
        }

        Log::info("Processing BYE match", [
            'match_id' => $match->id,
            'round' => $match->round_number,
            'position' => $match->bracket_position,
            'winner_id' => $winnerId,
            'next_match_id' => $match->next_match_id,
            'next_match_slot' => $match->next_match_slot,
        ]);

        // Update match as completed BYE
        // Scores are 0:0 for BYE - frontend should check match_type and display "BYE"
        $match->update([
            'match_type' => MatchType::BYE,
            'winner_id' => $winnerId,
            'status' => MatchStatus::COMPLETED,
            'player1_score' => 0,
            'player2_score' => 0,
            'played_at' => now(),
        ]);

        Log::info("BYE match marked complete", ['match_id' => $match->id]);

        // Advance winner to next match
        $this->advanceToNextMatch($match, $winnerId);
    }

    /**
     * Advance a winner to their next match.
     *
     * Note: We intentionally do NOT recursively process BYEs here.
     * A match in Round 2+ with only one player is NOT a BYE - it's
     * waiting for the winner of another match. True BYEs only exist
     * in Round 1 where bracket positions are intentionally empty.
     *
     * If two BYE winners meet in Round 2 (rare edge case), this should
     * be handled when match results are processed, not during generation.
     */
    protected function advanceToNextMatch(GameMatch $match, int $winnerId): void
    {
        Log::info("advanceToNextMatch called", [
            'from_match' => $match->id,
            'winner' => $winnerId,
            'next_match_id' => $match->next_match_id,
            'next_slot' => $match->next_match_slot,
        ]);

        if (!$match->next_match_id) {
            Log::info("No next match - this is the final");
            return;
        }

        $nextMatch = GameMatch::find($match->next_match_id);
        if (!$nextMatch) {
            Log::error("Next match not found!", ['next_match_id' => $match->next_match_id]);
            return;
        }

        Log::info("Next match BEFORE update", [
            'next_match_id' => $nextMatch->id,
            'round' => $nextMatch->round_number,
            'p1' => $nextMatch->player1_id,
            'p2' => $nextMatch->player2_id,
            'status' => $nextMatch->status->value,
        ]);

        $slot = $match->next_match_slot === 'player1' ? 'player1_id' : 'player2_id';
        $nextMatch->update([$slot => $winnerId]);

        Log::info("Next match AFTER update", [
            'next_match_id' => $nextMatch->id,
            'p1' => $nextMatch->player1_id,
            'p2' => $nextMatch->player2_id,
        ]);

        // NO recursive BYE processing - matches in Round 2+ are NOT BYEs,
        // they are TBD slots waiting for winners from other matches.
        Log::info("Advance complete - NO RECURSIVE BYE PROCESSING");
    }
}
