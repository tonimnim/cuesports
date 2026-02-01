<?php

namespace App\Jobs;

use App\Enums\GeographicLevel;
use App\Models\GeographicUnit;
use App\Models\PlayerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateCountryRanksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Optional: specific country ID to update ranks for.
     */
    public ?int $countryId;

    public function __construct(?int $countryId = null)
    {
        $this->countryId = $countryId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting country ranks update', ['country_id' => $this->countryId]);

        // First, ensure all players have their country_id set
        $this->updatePlayerCountryIds();

        // Get countries to process
        $countriesQuery = GeographicUnit::where('level', GeographicLevel::NATIONAL->value);

        if ($this->countryId) {
            $countriesQuery->where('id', $this->countryId);
        }

        $countries = $countriesQuery->get();

        $totalUpdated = 0;

        foreach ($countries as $country) {
            $updated = $this->updateRanksForCountry($country);
            $totalUpdated += $updated;
        }

        Log::info('Country ranks update completed', [
            'countries_processed' => $countries->count(),
            'players_updated' => $totalUpdated,
        ]);
    }

    /**
     * Ensure all player profiles have their country_id set.
     */
    protected function updatePlayerCountryIds(): void
    {
        // Get players without country_id set
        $playersWithoutCountry = PlayerProfile::whereNull('country_id')
            ->with('geographicUnit')
            ->get();

        foreach ($playersWithoutCountry as $player) {
            $country = $player->getCountry();
            if ($country) {
                $player->country_id = $country->id;
                $player->saveQuietly();
            }
        }

        if ($playersWithoutCountry->count() > 0) {
            Log::info('Updated country_id for players', [
                'count' => $playersWithoutCountry->count(),
            ]);
        }
    }

    /**
     * Update ranks for all players in a country.
     */
    protected function updateRanksForCountry(GeographicUnit $country): int
    {
        // Use PostgreSQL-compatible UPDATE with window function
        // Players are ranked by rating DESC (higher rating = better rank)
        DB::statement("
            UPDATE player_profiles
            SET country_rank = ranked.new_rank
            FROM (
                SELECT
                    id,
                    ROW_NUMBER() OVER (ORDER BY rating DESC, wins DESC, total_matches DESC, id ASC) as new_rank
                FROM player_profiles
                WHERE country_id = ?
            ) ranked
            WHERE player_profiles.id = ranked.id
              AND player_profiles.country_id = ?
        ", [$country->id, $country->id]);

        $playerCount = PlayerProfile::where('country_id', $country->id)->count();

        Log::info('Updated ranks for country', [
            'country' => $country->name,
            'country_id' => $country->id,
            'players' => $playerCount,
        ]);

        return $playerCount;
    }
}
