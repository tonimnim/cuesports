<?php

namespace App\Providers;

use App\Events\MatchDisputed;
use App\Events\MatchResolved;
use App\Events\MatchResultConfirmed;
use App\Events\MatchResultSubmitted;
use App\Events\TournamentStarted;
use App\Listeners\SendMatchDisputedNotification;
use App\Listeners\SendMatchResolvedNotification;
use App\Listeners\SendMatchResultConfirmedNotification;
use App\Listeners\SendMatchResultSubmittedNotification;
use App\Listeners\SendTournamentStartedNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TournamentStarted::class => [
            SendTournamentStartedNotifications::class,
        ],
        MatchResultSubmitted::class => [
            SendMatchResultSubmittedNotification::class,
        ],
        MatchResultConfirmed::class => [
            SendMatchResultConfirmedNotification::class,
        ],
        MatchDisputed::class => [
            SendMatchDisputedNotification::class,
        ],
        MatchResolved::class => [
            SendMatchResolvedNotification::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
