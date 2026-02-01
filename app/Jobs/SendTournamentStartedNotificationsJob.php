<?php

namespace App\Jobs;

use App\Models\Tournament;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTournamentStartedNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public Tournament $tournament
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notificationService): void
    {
        Log::info("Sending tournament started notifications for tournament {$this->tournament->id}");

        // Get all participant user IDs
        $participants = $this->tournament->participants()
            ->with('playerProfile.user')
            ->get();

        if ($participants->isEmpty()) {
            Log::info("No participants to notify for tournament {$this->tournament->id}");
            return;
        }

        // Get the users (filtering out any null values)
        $users = $participants
            ->map(fn($p) => $p->playerProfile?->user)
            ->filter()
            ->unique('id');

        // Calculate first round matches count
        $firstRoundMatches = $this->tournament->matches()
            ->where('round_number', 1)
            ->count();

        // Send bulk notification
        $sent = $notificationService->sendBulk(
            users: $users,
            type: 'tournament.started',
            title: 'Tournament Started!',
            message: "The {$this->tournament->name} has officially begun! " .
                "Check your first match in the bracket. Good luck!",
            icon: 'trophy',
            actionUrl: "/tournaments/{$this->tournament->slug}",
            actionText: 'View Bracket',
            data: [
                'tournament_id' => $this->tournament->id,
                'tournament_name' => $this->tournament->name,
                'tournament_slug' => $this->tournament->slug,
                'participants_count' => $this->tournament->participants_count,
                'first_round_matches' => $firstRoundMatches,
            ]
        );

        Log::info("Sent tournament started notifications to {$sent} participants for tournament {$this->tournament->id}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to send tournament started notifications for tournament {$this->tournament->id}: " .
            $exception->getMessage());
    }

    public function tags(): array
    {
        return [
            'notification',
            'tournament-started',
            "tournament:{$this->tournament->id}",
        ];
    }
}
