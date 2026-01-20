<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\MatchType;
use App\Models\Match;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MatchService
{
    public function __construct(
        protected RatingService $ratingService
    ) {}

    /**
     * Submit a match result.
     *
     * @throws \InvalidArgumentException
     */
    public function submitResult(
        Match $match,
        TournamentParticipant $submitter,
        int $submitterScore,
        int $opponentScore
    ): Match {
        if (!$match->canSubmitResult($submitter)) {
            throw new \InvalidArgumentException('Cannot submit result for this match.');
        }

        if (!$match->isValidScore($submitterScore, $opponentScore)) {
            throw new \InvalidArgumentException('Invalid score. Score must be valid for best of ' . $match->tournament->best_of);
        }

        return DB::transaction(function () use ($match, $submitter, $submitterScore, $opponentScore) {
            $match->submitResult($submitter, $submitterScore, $opponentScore);
            return $match->fresh();
        });
    }

    /**
     * Confirm a match result.
     *
     * @throws \InvalidArgumentException
     */
    public function confirmResult(
        Match $match,
        TournamentParticipant $confirmer
    ): Match {
        if (!$match->canConfirm($confirmer)) {
            throw new \InvalidArgumentException('Cannot confirm this match result.');
        }

        return DB::transaction(function () use ($match, $confirmer) {
            $match->confirm($confirmer);

            // Process rating changes
            $this->ratingService->processMatchResult($match);

            return $match->fresh();
        });
    }

    /**
     * Dispute a match result.
     *
     * @throws \InvalidArgumentException
     */
    public function disputeResult(
        Match $match,
        TournamentParticipant $disputer,
        string $reason
    ): Match {
        if (!$match->canDispute($disputer)) {
            throw new \InvalidArgumentException('Cannot dispute this match result.');
        }

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException('Dispute reason is required.');
        }

        return DB::transaction(function () use ($match, $disputer, $reason) {
            $match->dispute($disputer, $reason);
            return $match->fresh();
        });
    }

    /**
     * Resolve a disputed match (admin action).
     *
     * @throws \InvalidArgumentException
     */
    public function resolveDispute(
        Match $match,
        User $resolver,
        int $player1Score,
        int $player2Score,
        ?string $notes = null
    ): Match {
        if (!$match->isDisputed()) {
            throw new \InvalidArgumentException('Match is not disputed.');
        }

        if (!$resolver->is_support && !$resolver->is_super_admin) {
            throw new \InvalidArgumentException('Only support or admin can resolve disputes.');
        }

        if (!$match->isValidScore($player1Score, $player2Score)) {
            throw new \InvalidArgumentException('Invalid score.');
        }

        return DB::transaction(function () use ($match, $resolver, $player1Score, $player2Score, $notes) {
            $match->resolve($resolver, $player1Score, $player2Score, $notes);

            // Process rating changes
            $this->ratingService->processMatchResult($match);

            return $match->fresh();
        });
    }

    /**
     * Get pending matches for a player.
     */
    public function getPendingMatchesForPlayer(TournamentParticipant $participant): Collection
    {
        return Match::forPlayer($participant->id)
            ->where(function ($query) {
                $query->scheduled()
                    ->orWhere->pendingConfirmation();
            })
            ->with(['player1.playerProfile', 'player2.playerProfile', 'tournament'])
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Get matches requiring action from a player.
     */
    public function getMatchesRequiringAction(TournamentParticipant $participant): Collection
    {
        return Match::forPlayer($participant->id)
            ->where(function ($query) use ($participant) {
                // Scheduled matches (need to submit result)
                $query->scheduled()
                    // OR pending confirmation where player is not the submitter
                    ->orWhere(function ($q) use ($participant) {
                        $q->pendingConfirmation()
                            ->where('submitted_by', '!=', $participant->id);
                    });
            })
            ->with(['player1.playerProfile', 'player2.playerProfile', 'tournament'])
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Get all disputed matches (for support dashboard).
     */
    public function getDisputedMatches(): Collection
    {
        return Match::disputed()
            ->with([
                'player1.playerProfile',
                'player2.playerProfile',
                'tournament',
                'submittedBy.playerProfile',
                'disputedBy.playerProfile',
            ])
            ->orderBy('disputed_at')
            ->get();
    }

    /**
     * Get match history for a tournament.
     */
    public function getTournamentMatchHistory(Tournament $tournament): Collection
    {
        return Match::where('tournament_id', $tournament->id)
            ->completed()
            ->with(['player1.playerProfile', 'player2.playerProfile', 'winner.playerProfile'])
            ->orderBy('played_at', 'desc')
            ->get();
    }

    /**
     * Create a bye match for a player advancing without opposition.
     */
    public function createByeMatch(
        Tournament $tournament,
        TournamentParticipant $player,
        int $roundNumber,
        string $roundName,
        ?int $nextMatchId = null,
        ?string $nextMatchSlot = null,
        ?int $stageId = null,
        ?int $bracketPosition = null
    ): Match {
        return DB::transaction(function () use (
            $tournament, $player, $roundNumber, $roundName,
            $nextMatchId, $nextMatchSlot, $stageId, $bracketPosition
        ) {
            $match = Match::createByeMatch(
                $tournament,
                $player,
                $roundNumber,
                $roundName,
                $nextMatchId,
                $nextMatchSlot
            );

            if ($stageId) {
                $match->stage_id = $stageId;
            }

            if ($bracketPosition !== null) {
                $match->bracket_position = $bracketPosition;
            }

            $match->save();

            return $match;
        });
    }

    /**
     * Get bracket data for a tournament.
     */
    public function getTournamentBracket(Tournament $tournament): array
    {
        $matches = Match::where('tournament_id', $tournament->id)
            ->with(['player1.playerProfile', 'player2.playerProfile', 'winner.playerProfile'])
            ->orderBy('round_number')
            ->orderBy('bracket_position')
            ->get();

        $bracket = [];
        foreach ($matches as $match) {
            $bracket[$match->round_number][] = [
                'id' => $match->id,
                'bracket_position' => $match->bracket_position,
                'match_type' => $match->match_type->value,
                'status' => $match->status->value,
                'player1' => $match->player1 ? [
                    'id' => $match->player1->id,
                    'name' => $match->player1->playerProfile->display_name,
                    'score' => $match->player1_score,
                    'is_winner' => $match->winner_id === $match->player1_id,
                ] : null,
                'player2' => $match->player2 ? [
                    'id' => $match->player2->id,
                    'name' => $match->player2->playerProfile->display_name,
                    'score' => $match->player2_score,
                    'is_winner' => $match->winner_id === $match->player2_id,
                ] : null,
                'winner_id' => $match->winner_id,
                'next_match_id' => $match->next_match_id,
                'next_match_slot' => $match->next_match_slot,
            ];
        }

        return $bracket;
    }

    /**
     * Get match statistics for a tournament.
     */
    public function getTournamentMatchStats(Tournament $tournament): array
    {
        $matches = Match::where('tournament_id', $tournament->id)->get();

        return [
            'total' => $matches->count(),
            'scheduled' => $matches->where('status', MatchStatus::SCHEDULED)->count(),
            'pending_confirmation' => $matches->where('status', MatchStatus::PENDING_CONFIRMATION)->count(),
            'completed' => $matches->where('status', MatchStatus::COMPLETED)->count(),
            'disputed' => $matches->where('status', MatchStatus::DISPUTED)->count(),
            'expired' => $matches->where('status', MatchStatus::EXPIRED)->count(),
            'cancelled' => $matches->where('status', MatchStatus::CANCELLED)->count(),
            'byes' => $matches->where('match_type', MatchType::BYE)->count(),
        ];
    }
}
