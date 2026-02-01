<?php

namespace Database\Seeders;

use App\Enums\GeographicLevel;
use App\Enums\ParticipantStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use App\Models\GeographicUnit;
use App\Models\OrganizerProfile;
use App\Models\PlayerProfile;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Database\Seeder;

class CueSportsAfricaTournamentSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: Set up Admin as CueSports Africa organizer
        $this->command->info('Setting up CueSports Africa as system organizer...');

        $admin = User::where('is_super_admin', true)->first();

        if (!$admin) {
            $this->command->error('Admin user not found. Please run DatabaseSeeder first.');
            return;
        }

        // Make admin also an organizer
        if (!$admin->is_organizer) {
            $admin->is_organizer = true;
            $admin->save();
            $this->command->info('Admin user updated to also be an organizer.');
        }

        // Create or update organizer profile for admin
        $organizerProfile = OrganizerProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'organization_name' => 'CueSports Africa',
                'description' => 'The official CueSports Africa platform - organizing tournaments across the continent.',
                'is_active' => true,
                'tournaments_hosted' => 0,
            ]
        );

        $this->command->info("Organizer profile created: {$organizerProfile->organization_name}");

        // Step 2: Get Kenya for geographic scope
        $kenya = GeographicUnit::where('code', 'KE')
            ->where('level', GeographicLevel::NATIONAL->value)
            ->first();

        if (!$kenya) {
            $this->command->error('Kenya geographic unit not found. Please run GeographicUnitSeeder first.');
            return;
        }

        // Step 3: Get all Kutus players
        $kutus = GeographicUnit::where('code', 'KE-KRY-MW-KT-C1')->first();

        if (!$kutus) {
            $this->command->error('Kutus community not found. Please run GeographicUnitSeeder first.');
            return;
        }

        $kutusPlayers = PlayerProfile::where('geographic_unit_id', $kutus->id)->get();

        if ($kutusPlayers->isEmpty()) {
            $this->command->error('No Kutus players found. Please run TestPlayersSeeder first.');
            return;
        }

        $this->command->info("Found {$kutusPlayers->count()} players from Kutus community.");

        // Step 4: Create the tournament
        $this->command->info('Creating CueSports Africa tournament...');

        $tournament = Tournament::create([
            'name' => 'Kutus Pool Championship 2026',
            'description' => 'The inaugural Kutus Pool Championship organized by CueSports Africa. ' .
                'All pool players from Kutus community are invited to compete for the championship title ' .
                'and prove themselves as the best in the region.',
            'type' => TournamentType::REGULAR,
            'status' => TournamentStatus::REGISTRATION,
            'format' => TournamentFormat::KNOCKOUT,
            'geographic_scope_id' => $kenya->id,
            'venue_name' => 'Kutus Sports Center',
            'venue_address' => 'Kutus Town, Kirinyaga County, Kenya',
            'registration_opens_at' => now(),
            'registration_closes_at' => now()->addDays(7),
            'starts_at' => now()->addDays(8),
            'race_to' => 3,
            'finals_race_to' => 5,
            'confirmation_hours' => 24,
            'entry_fee' => 50000, // 500 KES in cents
            'entry_fee_currency' => 'KES',
            'requires_payment' => true,
            'created_by' => $admin->id,
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $this->command->info("Tournament created: {$tournament->name} (ID: {$tournament->id})");

        // Step 5: Register all Kutus players
        $this->command->info("Registering all {$kutusPlayers->count()} Kutus players...");

        $registered = 0;
        foreach ($kutusPlayers as $player) {
            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'player_profile_id' => $player->id,
                'status' => ParticipantStatus::REGISTERED,
                'seed' => null, // Will be seeded when tournament starts based on rating
                'registered_at' => now(),
            ]);

            $registered++;

            if ($registered % 50 === 0) {
                $this->command->info("Registered {$registered} players...");
            }
        }

        // Update tournament participant count
        $tournament->update(['participants_count' => $registered]);

        // Update organizer's tournament count
        $organizerProfile->increment('tournaments_hosted');

        $this->command->info('');
        $this->command->info('=== CueSports Africa Tournament Setup Complete ===');
        $this->command->info("Organizer: {$organizerProfile->organization_name}");
        $this->command->info("Tournament: {$tournament->name}");
        $this->command->info("Tournament ID: {$tournament->id}");
        $this->command->info("Participants: {$registered}");
        $this->command->info("Entry Fee: KES " . number_format($tournament->entry_fee / 100, 2));
        $this->command->info("Registration closes: {$tournament->registration_closes_at->format('Y-m-d H:i')}");
        $this->command->info("Tournament starts: {$tournament->starts_at->format('Y-m-d H:i')}");
        $this->command->info('');
        $this->command->info('The tournament is now open for registration!');
    }
}
