<?php

namespace App\Listeners;

use App\Events\MatchResultSubmitted;
use App\Events\MatchResultConfirmed;
use App\Events\MatchDisputed;
use App\Events\MatchResolved;
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
