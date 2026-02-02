<?php

namespace App\Listeners;

use App\Events\MatchDisputed;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatchDisputedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function handle(MatchDisputed $event): void
    {
        $match = $event->match;
        $match->loadMissing(['player1.playerProfile.user', 'player2.playerProfile.user', 'disputedBy.playerProfile']);

        $disputer = $match->disputedBy;
        $disputerName = $disputer?->playerProfile?->display_name ?? 'Opponent';

        // Notify the other player
        $other = $match->player1_id === $disputer?->id ? $match->player2 : $match->player1;

        if ($other?->playerProfile?->user) {
            $this->notificationService->sendMatchDisputed($other->playerProfile->user, $match, $disputerName);
        }
    }
}
