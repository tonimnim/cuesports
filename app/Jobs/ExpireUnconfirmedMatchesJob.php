<?php

namespace App\Jobs;

use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Services\Bracket\BracketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireUnconfirmedMatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Execute the job.
     */
    public function handle(BracketService $bracketService): void
    {
        $unconfirmedMatches = GameMatch::where('status', MatchStatus::PENDING_CONFIRMATION)
            ->whereNotNull('submitted_at')
            ->whereHas('tournament', function ($q) {
                $q->where('auto_confirm_results', true);
            })
            ->with(['player1.playerProfile', 'player2.playerProfile', 'tournament', 'submittedBy.playerProfile'])
            ->get()
            ->filter(function ($match) {
                return $match->isConfirmationExpired();
            });

        foreach ($unconfirmedMatches as $match) {
            try {
                DB::transaction(function () use ($match, $bracketService) {
                    $this->autoConfirmMatch($match, $bracketService);

                    Log::info("Match {$match->id} auto-confirmed after confirmation deadline", [
                        'tournament_id' => $match->tournament_id,
                        'winner' => $match->winner?->playerProfile?->display_name,
                        'submitted_by' => $match->submittedBy?->playerProfile?->display_name,
                        'score' => $match->getScoreDisplay(),
                    ]);
                });
            } catch (\Exception $e) {
                Log::error('Failed to auto-confirm match', [
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Auto-confirm a match when the confirmation window expires.
     * The submitted result is accepted as the final result.
     */
    protected function autoConfirmMatch(GameMatch $match, BracketService $bracketService): void
    {
        // Mark as confirmed (the opponent who should have confirmed)
        $opponent = $match->submitted_by === $match->player1_id
            ? $match->player2
            : $match->player1;

        $match->confirmed_by = $opponent?->id;
        $match->confirmed_at = now();
        $match->played_at = $match->submitted_at ?? now();
        $match->status = MatchStatus::COMPLETED;

        // Determine winner and loser based on submitted scores
        if ($match->player1_score > $match->player2_score) {
            $match->winner_id = $match->player1_id;
            $match->loser_id = $match->player2_id;
        } else {
            $match->winner_id = $match->player2_id;
            $match->loser_id = $match->player1_id;
        }

        $match->save();

        // Update participant stats
        $this->updateParticipantStats($match);

        // Advance winner to next match
        if ($match->winner_id && $match->next_match_id) {
            $bracketService->advanceWinner($match);
        }

        // Handle semi-finals losers → third-place match
        $bracketService->handleSemiFinalsCompletion($match);

        // Check if tournament should be completed
        $this->checkTournamentCompletion($match->tournament, $bracketService);
    }

    /**
     * Check if tournament should be completed after this match.
     */
    protected function checkTournamentCompletion($tournament, BracketService $bracketService): void
    {
        if ($bracketService->isBracketComplete($tournament)) {
            $bracketService->calculateFinalPositions($tournament);
            $tournament->complete();

            Log::info("Tournament {$tournament->id} auto-completed after final match", [
                'tournament_name' => $tournament->name,
            ]);
        }
    }

    /**
     * Update participant stats after match completion.
     */
    protected function updateParticipantStats(GameMatch $match): void
    {
        // Update player 1 tournament participant stats
        $match->player1?->recordMatchResult(
            $match->player1_score,
            $match->player2_score,
            $match->winner_id === $match->player1_id
        );

        // Update player 2 tournament participant stats (if not a bye)
        if ($match->player2) {
            $match->player2->recordMatchResult(
                $match->player2_score,
                $match->player1_score,
                $match->winner_id === $match->player2_id
            );
        }
    }
}
