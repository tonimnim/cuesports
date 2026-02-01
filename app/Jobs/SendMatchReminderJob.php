<?php

namespace App\Jobs;

use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMatchReminderJob implements ShouldQueue
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
    public function handle(NotificationService $notificationService): void
    {
        // Find matches with 24 hours remaining
        $this->sendReminders(24, $notificationService);

        // Find matches with 48 hours remaining
        $this->sendReminders(48, $notificationService);
    }

    /**
     * Send reminders for matches with the specified hours remaining.
     */
    protected function sendReminders(int $hoursRemaining, NotificationService $notificationService): void
    {
        $windowStart = now()->addHours($hoursRemaining);
        $windowEnd = now()->addHours($hoursRemaining + 1);

        $matches = GameMatch::where('status', MatchStatus::SCHEDULED)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$windowStart, $windowEnd])
            ->with(['player1.playerProfile.user', 'player2.playerProfile.user', 'tournament'])
            ->get();

        foreach ($matches as $match) {
            try {
                // Notify player 1
                if ($match->player1?->playerProfile?->user) {
                    $this->sendMatchReminder(
                        $notificationService,
                        $match->player1->playerProfile->user,
                        $match,
                        $hoursRemaining
                    );
                }

                // Notify player 2
                if ($match->player2?->playerProfile?->user) {
                    $this->sendMatchReminder(
                        $notificationService,
                        $match->player2->playerProfile->user,
                        $match,
                        $hoursRemaining
                    );
                }

                Log::info("Sent {$hoursRemaining}h reminder for match {$match->id}", [
                    'tournament_id' => $match->tournament_id,
                    'player1' => $match->player1?->playerProfile?->display_name,
                    'player2' => $match->player2?->playerProfile?->display_name,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send {$hoursRemaining}h reminder for match {$match->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send a match reminder notification to a user.
     */
    protected function sendMatchReminder(
        NotificationService $notificationService,
        $user,
        GameMatch $match,
        int $hoursRemaining
    ): void {
        $tournamentName = $match->tournament->name ?? 'Tournament';
        $opponentName = $this->getOpponentName($match, $user);

        $notificationService->send(
            $user,
            'match_reminder',
            "Match Reminder - {$hoursRemaining} Hours Left",
            "Your match in {$tournamentName} against {$opponentName} expires in {$hoursRemaining} hours. Please submit your result before the deadline.",
            'clock',
            "/tournaments/{$match->tournament_id}/matches/{$match->id}",
            'View Match',
            [
                'match_id' => $match->id,
                'tournament_id' => $match->tournament_id,
                'hours_remaining' => $hoursRemaining,
            ]
        );
    }

    /**
     * Get the opponent's display name for a user in a match.
     */
    protected function getOpponentName(GameMatch $match, $user): string
    {
        $userProfileId = $user->playerProfile?->id;

        if ($match->player1?->player_profile_id === $userProfileId) {
            return $match->player2?->playerProfile?->display_name ?? 'TBD';
        }

        return $match->player1?->playerProfile?->display_name ?? 'TBD';
    }
}
