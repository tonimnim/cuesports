<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register broadcasting routes with API authentication
        Broadcast::routes(['middleware' => ['api', 'auth:api']]);

        // Load channel authorization definitions
        require base_path('routes/channels.php');
    }
}
