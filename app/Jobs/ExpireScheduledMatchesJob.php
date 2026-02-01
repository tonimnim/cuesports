<?php

namespace App\Jobs;

use App\Enums\ForfeitType;
use App\Enums\MatchStatus;
use App\Enums\MatchType;
use App\Models\GameMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireScheduledMatchesJob implements ShouldQueue
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
        $expiredMatches = GameMatch::where('status', MatchStatus::SCHEDULED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('match_type', '!=', MatchType::BYE)
            ->with(['tournament', 'player1.playerProfile', 'player2.playerProfile'])
            ->get();

        foreach ($expiredMatches as $match) {
            try {
                DB::transaction(function () use ($match) {
                    $match->status = MatchStatus::EXPIRED;
                    $match->forfeit_type = ForfeitType::DOUBLE_FORFEIT;
                    $match->save();

                    // Check if tournament has double_forfeit_on_expiry setting
                    // Default to true if not set
                    $doubleForfeitOnExpiry = $match->tournament->double_forfeit_on_expiry ?? true;

                    if ($doubleForfeitOnExpiry) {
                        // Eliminate both players
                        $this->eliminateBothPlayers($match);
                    }

                    // Handle next match - mark it as needing attention or give bye
                    $this->handleNextMatch($match);

                    Log::info("Match {$match->id} expired - double forfeit", [
                        'tournament_id' => $match->tournament_id,
                        'player1' => $match->player1?->playerProfile?->display_name,
                        'player2' => $match->player2?->playerProfile?->display_name,
                        'double_forfeit_elimination' => $doubleForfeitOnExpiry,
                    ]);
                });
            } catch (\Exception $e) {
                Log::error('Failed to expire match', [
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Eliminate both players from the tournament due to double forfeit.
     */
    protected function eliminateBothPlayers(GameMatch $match): void
    {
        // Calculate position based on round
        $remainingInRound = GameMatch::where('tournament_id', $match->tournament_id)
            ->where('round_number', $match->round_number)
            ->whereIn('status', [MatchStatus::SCHEDULED, MatchStatus::PENDING_CONFIRMATION])
            ->count();

        $totalParticipants = $match->tournament->participants()->count();
        $position = $totalParticipants - $remainingInRound + 1;

        if ($match->player1) {
            $match->player1->eliminate($position);
        }
        if ($match->player2) {
            $match->player2->eliminate($position);
        }
    }

    /**
     * Handle the next match when a double forfeit occurs.
     */
    protected function handleNextMatch(GameMatch $match): void
    {
        if ($match->next_match_id) {
            // The next match will have an empty slot due to double forfeit
            // This could become a bye or require organizer intervention
            Log::warning("Match {$match->next_match_id} has empty slot due to double forfeit", [
                'expired_match_id' => $match->id,
                'tournament_id' => $match->tournament_id,
            ]);
        }
    }
}
