<?php

namespace App\Console\Commands;

use App\Enums\GeographicLevel;
use App\Models\GeographicUnit;
use Illuminate\Console\Command;

class BackfillGeographyCountryCode extends Command
{
    protected $signature = 'geography:backfill-country-codes';
    protected $description = 'Backfill country_code and local_term for all geographic units';

    public function handle(): int
    {
        $this->info('Backfilling country_code and local_term for geographic units...');

        // Get all countries
        $countries = GeographicUnit::where('level', GeographicLevel::NATIONAL->value)->get();

        $total = 0;

        foreach ($countries as $country) {
            $countryCode = $country->code;
            $this->info("Processing {$country->name} ({$countryCode})...");

            // Update the country itself
            $country->update([
                'country_code' => $countryCode,
                'local_term' => GeographicLevel::NATIONAL->labelForCountry($countryCode),
            ]);

            // Update all descendants
            $updated = $this->updateDescendants($country, $countryCode);
            $total += $updated + 1;

            $this->info("  Updated {$updated} descendants");
        }

        $this->info("Done! Updated {$total} geographic units.");

        return Command::SUCCESS;
    }

    private function updateDescendants(GeographicUnit $parent, string $countryCode): int
    {
        $count = 0;

        $children = $parent->children()->get();

        foreach ($children as $child) {
            $levelEnum = GeographicLevel::from($child->level);
            $localTerm = $levelEnum->labelForCountry($countryCode);

            $child->update([
                'country_code' => $countryCode,
                'local_term' => $localTerm,
            ]);

            $count++;
            $count += $this->updateDescendants($child, $countryCode);
        }

        return $count;
    }
}
