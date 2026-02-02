<?php

namespace App\Listeners;

use App\Events\MatchResultConfirmed;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatchResultConfirmedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function handle(MatchResultConfirmed $event): void
    {
        $match = $event->match;
        $match->loadMissing(['player1.playerProfile.user', 'player2.playerProfile.user', 'winner']);

        // Notify both players
        if ($match->player1?->playerProfile?->user) {
            $won = $match->winner_id === $match->player1_id;
            $this->notificationService->sendResultConfirmed($match->player1->playerProfile->user, $match, $won);
        }

        if ($match->player2?->playerProfile?->user) {
            $won = $match->winner_id === $match->player2_id;
            $this->notificationService->sendResultConfirmed($match->player2->playerProfile->user, $match, $won);
        }
    }
}
