<?php

namespace App\Listeners;

use App\Events\MatchResolved;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatchResolvedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function handle(MatchResolved $event): void
    {
        $match = $event->match;
        $match->loadMissing(['player1.playerProfile.user', 'player2.playerProfile.user', 'winner']);

        // Notify both players about the resolution
        if ($match->player1?->playerProfile?->user) {
            $won = $match->winner_id === $match->player1_id;
            $this->notificationService->sendMatchResolved($match->player1->playerProfile->user, $match, $won);
        }

        if ($match->player2?->playerProfile?->user) {
            $won = $match->winner_id === $match->player2_id;
            $this->notificationService->sendMatchResolved($match->player2->playerProfile->user, $match, $won);
        }
    }
}
