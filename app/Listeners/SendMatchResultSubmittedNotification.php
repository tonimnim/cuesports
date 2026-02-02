<?php

namespace App\Listeners;

use App\Events\MatchResultSubmitted;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMatchResultSubmittedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function handle(MatchResultSubmitted $event): void
    {
        $match = $event->match;
        $match->loadMissing(['player1.playerProfile.user', 'player2.playerProfile.user', 'submittedBy.playerProfile']);

        $submitter = $match->submittedBy;
        $submitterName = $submitter?->playerProfile?->display_name ?? 'Opponent';
        $score = "{$match->player1_score} - {$match->player2_score}";

        // Notify the opponent (non-submitter)
        $opponent = $match->player1_id === $submitter?->id ? $match->player2 : $match->player1;

        if ($opponent?->playerProfile?->user) {
            $this->notificationService->sendResultSubmitted(
                $opponent->playerProfile->user,
                $match,
                $submitterName,
                $score
            );
        }
    }
}
