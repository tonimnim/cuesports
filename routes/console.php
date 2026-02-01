<?php

use App\Jobs\CheckServiceStatusJob;
use App\Jobs\ExpireScheduledMatchesJob;
use App\Jobs\ExpireUnconfirmedMatchesJob;
use App\Jobs\SendMatchReminderJob;
use App\Jobs\UpdateCountryRanksJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule match expiry jobs
// Run hourly to check for expired matches
Schedule::job(new ExpireScheduledMatchesJob())
    ->hourly()
    ->name('expire-scheduled-matches')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new ExpireUnconfirmedMatchesJob())
    ->hourly()
    ->name('expire-unconfirmed-matches')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SendMatchReminderJob())
    ->dailyAt('09:00')
    ->name('send-match-reminders')
    ->withoutOverlapping()
    ->onOneServer();

// Service status monitoring - run every minute
Schedule::job(new CheckServiceStatusJob())
    ->everyMinute()
    ->name('check-service-status')
    ->withoutOverlapping()
    ->onOneServer();

// Update country rankings - run hourly
Schedule::job(new UpdateCountryRanksJob())
    ->hourly()
    ->name('update-country-ranks')
    ->withoutOverlapping()
    ->onOneServer();
