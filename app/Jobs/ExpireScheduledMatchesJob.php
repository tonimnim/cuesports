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
        $expiredMatches = Match::scheduled()
            ->where('expires_at', '<=', now())
            ->with(['player1.playerProfile', 'player2.playerProfile', 'tournament'])
            ->get();

        $count = 0;

        foreach ($expiredMatches as $match) {
            try {
                $match->expire();
                $count++;

                Log::info('Match expired', [
                    'match_id' => $match->id,
                    'tournament_id' => $match->tournament_id,
                    'player1' => $match->player1?->playerProfile?->display_name,
                    'player2' => $match->player2?->playerProfile?->display_name,
                    'match_type' => $match->match_type->value,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to expire match', [
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($count > 0) {
            Log::info("Expired {$count} scheduled matches");
        }
    }
}
