<?php

namespace App\Services\Bracket;

use App\Contracts\BracketGeneratorInterface;
use App\Contracts\BracketResult;
use App\Enums\MatchStatus;
use App\Enums\MatchType;
use App\Models\GameMatch;
use App\Models\Tournament;
use Illuminate\Support\Facades\Log;

/**
 * Main entry point for bracket operations.
 *
 * Uses Strategy pattern to delegate to appropriate generator
 * based on tournament format.
 */
class BracketService
{
    /**
     * @var array<BracketGeneratorInterface>
     */
    protected array $generators = [];

    public function __construct(
        protected PositionCalculator $positionCalculator
    ) {}

    /**
     * Register a bracket generator.
     */
    public function registerGenerator(BracketGeneratorInterface $generator): self
    {
        $this->generators[] = $generator;
        return $this;
    }

    /**
     * Generate bracket for a tournament.
     *
     * Finds the appropriate generator and delegates to it.
     *
     * @throws \RuntimeException If no suitable generator is found
     */
    public function generate(Tournament $tournament): BracketResult
    {
        $generator = $this->findGenerator($tournament);

        if (!$generator) {
            throw new \RuntimeException(
                "No bracket generator found for tournament format: {$tournament->format->value}"
            );
        }

        Log::info("Using generator: " . get_class($generator) . " for tournament {$tournament->id}");

        return $generator->generate($tournament);
    }

    /**
     * Advance winner to next match.
     */
    public function advanceWinner(GameMatch $match): void
    {
        $generator = $this->findGenerator($match->tournament);

        if ($generator) {
            $generator->advanceWinner($match);
        }
    }

    /**
     * Check if a tournament can be started.
     */
    public function canStartTournament(Tournament $tournament): bool
    {
        $generator = $this->findGenerator($tournament);

        if (!$generator) {
            return false;
        }

        $participantCount = $tournament->participants()->count();
        return $participantCount >= $generator->getMinimumParticipants();
    }

    /**
     * Get minimum participants required for a tournament.
     */
    public function getMinimumParticipants(Tournament $tournament): int
    {
        $generator = $this->findGenerator($tournament);
        return $generator?->getMinimumParticipants() ?? 2;
    }

    /**
     * Find the appropriate generator for a tournament.
     */
    protected function findGenerator(Tournament $tournament): ?BracketGeneratorInterface
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($tournament)) {
                return $generator;
            }
        }

        return null;
    }

    /**
     * Get bracket visualization data.
     *
     * Returns structured data for frontend bracket display.
     */
    public function getBracketData(Tournament $tournament): array
    {
        $matches = $tournament->matches()
            ->with(['player1.playerProfile', 'player2.playerProfile', 'winner'])
            ->orderBy('round_number')
            ->orderBy('bracket_position')
            ->get();

        $rounds = [];
        foreach ($matches as $match) {
            $roundKey = $match->round_number;

            if (!isset($rounds[$roundKey])) {
                $rounds[$roundKey] = [
                    'round' => $match->round_number,
                    'round_name' => $match->round_name,
                    'matches' => [],
                ];
            }

            $rounds[$roundKey]['matches'][] = $this->formatMatchForBracket($match);
        }

        return [
            'tournament_id' => $tournament->id,
            'total_rounds' => count($rounds),
            'rounds' => array_values($rounds),
        ];
    }

    /**
     * Format a match for bracket display.
     */
    protected function formatMatchForBracket(GameMatch $match): array
    {
        return [
            'id' => $match->id,
            'round_number' => $match->round_number,
            'bracket_position' => $match->bracket_position,
            'match_type' => $match->match_type?->value,
            'status' => $match->status->value,
            'player1' => $match->player1 ? [
                'id' => $match->player1_id,
                'name' => $match->player1->playerProfile?->display_name ?? 'Unknown',
                'seed' => $match->player1->seed,
                'score' => $match->player1_score,
            ] : null,
            'player2' => $match->player2 ? [
                'id' => $match->player2_id,
                'name' => $match->player2->playerProfile?->display_name ?? 'Unknown',
                'seed' => $match->player2->seed,
                'score' => $match->player2_score,
            ] : null,
            'winner_id' => $match->winner_id,
            'next_match_id' => $match->next_match_id,
            'next_match_slot' => $match->next_match_slot,
        ];
    }

    /**
     * Check if all bracket matches are complete.
     */
    public function isBracketComplete(Tournament $tournament): bool
    {
        $pendingMatches = $tournament->matches()
            ->whereIn('status', [MatchStatus::SCHEDULED, MatchStatus::PENDING_CONFIRMATION])
            ->where('match_type', '!=', MatchType::BYE)
            ->count();

        return $pendingMatches === 0;
    }

    /**
     * Handle semi-final completion - assign losers to third-place match.
     */
    public function handleSemiFinalsCompletion(GameMatch $match): void
    {
        if ($match->match_type !== MatchType::SEMI_FINAL || !$match->loser_id) {
            return;
        }

        $tournament = $match->tournament;
        $thirdPlaceMatch = $tournament->matches()
            ->where('match_type', MatchType::THIRD_PLACE)
            ->first();

        if (!$thirdPlaceMatch) {
            return;
        }

        // Assign loser to third-place match
        if (!$thirdPlaceMatch->player1_id) {
            $thirdPlaceMatch->update(['player1_id' => $match->loser_id]);
        } elseif (!$thirdPlaceMatch->player2_id) {
            $thirdPlaceMatch->update(['player2_id' => $match->loser_id]);
        }
    }

    /**
     * Calculate and assign final positions after tournament completion.
     *
     * Uses PositionCalculator to determine all positions:
     * - 1st-4th: Determined by Final and 3rd Place matches
     * - 5th+: Determined by statistical tiebreakers
     */
    public function calculateFinalPositions(Tournament $tournament): void
    {
        $this->positionCalculator->calculate($tournament);
    }
}
