<?php

namespace App\Jobs;

use App\Enums\MatchStatus;
use App\Models\Match;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    public function handle(): void
    {
        // Find matches that are pending confirmation and past their confirmation deadline
        $expiredMatches = Match::pendingConfirmation()
            ->whereNotNull('submitted_at')
            ->with(['player1.playerProfile', 'player2.playerProfile', 'tournament'])
            ->get()
            ->filter(function ($match) {
                return $match->isConfirmationExpired();
            });

        $count = 0;

        foreach ($expiredMatches as $match) {
            try {
                // Auto-confirm the match if confirmation window expired
                // The submitter's result stands since opponent didn't respond
                $this->autoConfirmMatch($match);
                $count++;

                Log::info('Match auto-confirmed due to expired confirmation window', [
                    'match_id' => $match->id,
                    'tournament_id' => $match->tournament_id,
                    'submitted_by' => $match->submittedBy?->playerProfile?->display_name,
                    'score' => $match->getScoreDisplay(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to auto-confirm match', [
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($count > 0) {
            Log::info("Auto-confirmed {$count} unconfirmed matches");
        }
    }

    /**
     * Auto-confirm a match when the confirmation window expires.
     * The submitted result is accepted as the final result.
     */
    protected function autoConfirmMatch(Match $match): void
    {
        // Mark as confirmed (the opponent who should have confirmed)
        $opponent = $match->submitted_by === $match->player1_id
            ? $match->player2
            : $match->player1;

        $match->confirmed_by = $opponent?->id;
        $match->confirmed_at = now();
        $match->played_at = $match->submitted_at;
        $match->status = MatchStatus::COMPLETED;

        // Determine winner and loser
        if ($match->player1_score > $match->player2_score) {
            $match->winner_id = $match->player1_id;
            $match->loser_id = $match->player2_id;
        } else {
            $match->winner_id = $match->player2_id;
            $match->loser_id = $match->player1_id;
        }

        $match->save();

        // Update participant stats
        $match->player1?->recordMatchResult(
            $match->player1_score,
            $match->player2_score,
            $match->winner_id === $match->player1_id
        );

        if ($match->player2) {
            $match->player2->recordMatchResult(
                $match->player2_score,
                $match->player1_score,
                $match->winner_id === $match->player2_id
            );
        }

        // Advance winner to next match
        if ($match->next_match_id && $match->winner_id) {
            $nextMatch = $match->nextMatch;
            if ($match->next_match_slot === 'player1') {
                $nextMatch->player1_id = $match->winner_id;
            } else {
                $nextMatch->player2_id = $match->winner_id;
            }
            $nextMatch->save();
        }
    }
}
