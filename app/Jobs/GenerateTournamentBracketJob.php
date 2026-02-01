<?php

namespace App\Jobs;

use App\Enums\ParticipantStatus;
use App\Events\TournamentStarted;
use App\Models\Tournament;
use App\Services\Bracket\BracketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for generating tournament brackets.
 *
 * Runs on the 'matches' queue to handle bracket generation
 * asynchronously, allowing for scalability with large tournaments.
 */
class GenerateTournamentBracketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 2;

    public function __construct(
        public Tournament $tournament,
        public int $startedByUserId
    ) {
        $this->onQueue('matches');
    }

    /**
     * Execute the job.
     */
    public function handle(BracketService $bracketService): void
    {
        Log::info("Starting bracket generation for tournament {$this->tournament->id}: {$this->tournament->name}");

        try {
            // Generate the bracket using the appropriate strategy
            $result = $bracketService->generate($this->tournament);

            // Update tournament with bracket info
            $this->tournament->update([
                'matches_count' => $result->matchesCreated,
            ]);

            // Activate tournament
            $this->tournament->activate();

            // Activate all participants
            $this->tournament->participants()->update([
                'status' => ParticipantStatus::ACTIVE,
            ]);

            Log::info("Bracket generated successfully for tournament {$this->tournament->id}", [
                'bracket_size' => $result->bracketSize,
                'total_rounds' => $result->totalRounds,
                'matches_created' => $result->matchesCreated,
                'bye_count' => $result->byeCount,
                'bye_matches_processed' => $result->byeMatchesProcessed,
            ]);

            // Fire the TournamentStarted event for notifications
            event(new TournamentStarted($this->tournament));

        } catch (\Exception $e) {
            Log::error("Failed to generate bracket for tournament {$this->tournament->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Bracket generation job failed permanently for tournament {$this->tournament->id}: " .
            $exception->getMessage());

        // Revert tournament status back to REGISTRATION if it was changed
        if ($this->tournament->isActive()) {
            $this->tournament->update(['status' => 'registration']);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'tournament',
            'bracket-generation',
            "tournament:{$this->tournament->id}",
        ];
    }
}
