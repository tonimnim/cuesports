<?php

namespace App\Services\Bracket;

use App\Enums\MatchStatus;
use App\Enums\MatchType;
use App\Models\GameMatch;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Calculates final positions for all tournament participants.
 *
 * Positions 1-4 are determined by Final and 3rd Place matches.
 * Positions 5+ are determined by statistical tiebreakers:
 *   1. Round eliminated (later round = better position)
 *   2. Frame difference in tournament (higher = better)
 *   3. Total frames won (higher = better)
 *   4. Original seed (lower seed = better)
 */
class PositionCalculator
{
    /**
     * Calculate and assign final positions for all participants.
     */
    public function calculate(Tournament $tournament): void
    {
        $participants = $tournament->participants()->get();
        $matches = $tournament->matches()
            ->where('status', MatchStatus::COMPLETED)
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        // Build participant stats from matches
        $stats = $this->buildParticipantStats($participants, $matches);

        // Group by elimination round (null = still in / winner)
        $grouped = $stats->groupBy('eliminated_round');

        // Calculate positions
        $position = 1;

        // 1st place: Final winner
        $finalMatch = $matches->firstWhere('match_type', MatchType::FINAL);
        if ($finalMatch?->winner_id) {
            $this->assignPosition($finalMatch->winner_id, 1);
            $position++;
        }

        // 2nd place: Final loser
        if ($finalMatch?->loser_id) {
            $this->assignPosition($finalMatch->loser_id, 2);
            $position++;
        }

        // 3rd place: 3rd place match winner
        $thirdPlaceMatch = $matches->firstWhere('match_type', MatchType::THIRD_PLACE);
        if ($thirdPlaceMatch?->winner_id) {
            $this->assignPosition($thirdPlaceMatch->winner_id, 3);
            $position++;
        }

        // 4th place: 3rd place match loser
        if ($thirdPlaceMatch?->loser_id) {
            $this->assignPosition($thirdPlaceMatch->loser_id, 4);
            $position++;
        }

        // 5th onwards: Use tiebreakers within each elimination round
        $processedIds = collect([
            $finalMatch?->winner_id,
            $finalMatch?->loser_id,
            $thirdPlaceMatch?->winner_id,
            $thirdPlaceMatch?->loser_id,
        ])->filter()->toArray();

        // Get remaining participants sorted by elimination round (desc) then tiebreakers
        $remaining = $stats
            ->reject(fn($s) => in_array($s['participant_id'], $processedIds))
            ->sortBy([
                ['eliminated_round', 'desc'],  // Later round = better
                ['frame_difference', 'desc'],  // Higher diff = better
                ['frames_won', 'desc'],        // More frames = better
                ['seed', 'asc'],               // Lower seed = better
            ])
            ->values();

        foreach ($remaining as $stat) {
            $this->assignPosition($stat['participant_id'], $position);
            $position++;
        }

        Log::info("Calculated final positions for tournament {$tournament->id}", [
            'total_participants' => $participants->count(),
            'positions_assigned' => $position - 1,
        ]);
    }

    /**
     * Build statistics for each participant from their matches.
     *
     * @return Collection<int, array>
     */
    protected function buildParticipantStats(Collection $participants, Collection $matches): Collection
    {
        return $participants->map(function (TournamentParticipant $participant) use ($matches) {
            $participantId = $participant->id;

            // Find all matches this participant played
            $playerMatches = $matches->filter(function (GameMatch $match) use ($participantId) {
                return $match->player1_id === $participantId || $match->player2_id === $participantId;
            });

            // Skip BYE matches for stats
            $realMatches = $playerMatches->reject(fn($m) => $m->match_type === MatchType::BYE);

            // Calculate frame stats
            $framesWon = 0;
            $framesLost = 0;

            foreach ($realMatches as $match) {
                if ($match->player1_id === $participantId) {
                    $framesWon += $match->player1_score ?? 0;
                    $framesLost += $match->player2_score ?? 0;
                } else {
                    $framesWon += $match->player2_score ?? 0;
                    $framesLost += $match->player1_score ?? 0;
                }
            }

            // Find elimination round (the round where they lost, excluding 3rd place match)
            $lostMatch = $realMatches
                ->reject(fn($m) => $m->match_type === MatchType::THIRD_PLACE)
                ->first(fn($m) => $m->winner_id && $m->winner_id !== $participantId);

            $eliminatedRound = $lostMatch?->round_number;

            // If they won the tournament, they weren't eliminated
            $finalMatch = $matches->firstWhere('match_type', MatchType::FINAL);
            if ($finalMatch?->winner_id === $participantId) {
                $eliminatedRound = null; // Winner
            }

            return [
                'participant_id' => $participantId,
                'seed' => $participant->seed ?? 999,
                'eliminated_round' => $eliminatedRound,
                'frames_won' => $framesWon,
                'frames_lost' => $framesLost,
                'frame_difference' => $framesWon - $framesLost,
                'matches_played' => $realMatches->count(),
            ];
        });
    }

    /**
     * Assign final position to a participant.
     */
    protected function assignPosition(int $participantId, int $position): void
    {
        TournamentParticipant::where('id', $participantId)
            ->update(['final_position' => $position]);
    }

    /**
     * Get position suffix (1st, 2nd, 3rd, 4th, etc.)
     */
    public static function formatPosition(int $position): string
    {
        $suffix = match ($position % 10) {
            1 => $position % 100 === 11 ? 'th' : 'st',
            2 => $position % 100 === 12 ? 'th' : 'nd',
            3 => $position % 100 === 13 ? 'th' : 'rd',
            default => 'th',
        };

        return $position . $suffix;
    }
}
