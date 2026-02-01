<?php

namespace App\Listeners;

use App\Events\TournamentStarted;
use App\Jobs\SendTournamentStartedNotificationsJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTournamentStartedNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(TournamentStarted $event): void
    {
        // Dispatch the notification job to the queue
        SendTournamentStartedNotificationsJob::dispatch($event->tournament);
    }
}
