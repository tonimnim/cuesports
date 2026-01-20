<?php

use App\Jobs\ExpireScheduledMatchesJob;
use App\Jobs\ExpireUnconfirmedMatchesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule match expiry jobs
// Run every 5 minutes to check for expired matches
Schedule::job(new ExpireScheduledMatchesJob())
    ->everyFiveMinutes()
    ->name('expire-scheduled-matches')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new ExpireUnconfirmedMatchesJob())
    ->everyFiveMinutes()
    ->name('expire-unconfirmed-matches')
    ->withoutOverlapping()
    ->onOneServer();
