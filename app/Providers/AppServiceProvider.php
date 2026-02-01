<?php

namespace App\Providers;

use App\Contracts\SeederInterface;
use App\Models\GameMatch;
use App\Services\Bracket\BracketService;
use App\Services\Bracket\BracketStructureBuilder;
use App\Services\Bracket\ByeProcessor;
use App\Services\Bracket\TraditionalSeeder;
use App\Services\Bracket\PositionCalculator;
use App\Services\Bracket\SingleEliminationGenerator;
use App\Services\NotificationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register bracket generation services
        $this->registerBracketServices();

        // Register NotificationService as singleton
        $this->app->singleton(NotificationService::class);
    }

    /**
     * Register bracket generation services with proper dependency injection.
     */
    protected function registerBracketServices(): void
    {
        // Register the structure builder as singleton (stateless)
        $this->app->singleton(BracketStructureBuilder::class);

        // Register the BYE processor
        $this->app->singleton(ByeProcessor::class, function ($app) {
            return new ByeProcessor(
                $app->make(BracketStructureBuilder::class)
            );
        });

        // Register the default seeder (traditional - sorted by rating)
        $this->app->bind(SeederInterface::class, TraditionalSeeder::class);

        // Register the single elimination generator
        $this->app->singleton(SingleEliminationGenerator::class, function ($app) {
            return new SingleEliminationGenerator(
                $app->make(SeederInterface::class),
                $app->make(BracketStructureBuilder::class),
                $app->make(ByeProcessor::class)
            );
        });

        // Register the position calculator
        $this->app->singleton(PositionCalculator::class);

        // Register the main BracketService with generators
        $this->app->singleton(BracketService::class, function ($app) {
            $service = new BracketService(
                $app->make(PositionCalculator::class)
            );

            // Register all available generators
            $service->registerGenerator($app->make(SingleEliminationGenerator::class));

            // Future: Add double elimination, round robin, etc.
            // $service->registerGenerator($app->make(DoubleEliminationGenerator::class));
            // $service->registerGenerator($app->make(RoundRobinGenerator::class));

            return $service;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure Passport token expiration
        Passport::tokensExpireIn(now()->addDays(30));
        Passport::refreshTokensExpireIn(now()->addDays(60));
        Passport::personalAccessTokensExpireIn(now()->addDays(30));

        // Route model binding for GameMatch (since model is named differently than route parameter)
        Route::model('match', GameMatch::class);

        // Handle stale database connections in Octane
        // This ensures that if a connection becomes stale, we reconnect
        if ($this->app->bound('octane')) {
            $this->app['events']->listen('Illuminate\Database\Events\ConnectionLost', function () {
                DB::reconnect();
            });
        }
    }
}
