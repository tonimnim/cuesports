<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\GeographicLevel;
use App\Enums\ParticipantStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use App\Models\GeographicUnit;
use App\Models\PlayerProfile;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestTournamentSeeder extends Seeder
{
    private array $firstNames = [
        'James', 'John', 'Robert', 'Michael', 'David', 'William', 'Richard', 'Joseph', 'Thomas', 'Charles',
        'Christopher', 'Daniel', 'Matthew', 'Anthony', 'Mark', 'Donald', 'Steven', 'Paul', 'Andrew', 'Joshua',
        'Kenneth', 'Kevin', 'Brian', 'George', 'Timothy', 'Ronald', 'Edward', 'Jason', 'Jeffrey', 'Ryan',
        'Jacob', 'Gary', 'Nicholas', 'Eric', 'Jonathan', 'Stephen', 'Larry', 'Justin', 'Scott', 'Brandon',
        'Benjamin', 'Samuel', 'Raymond', 'Gregory', 'Frank', 'Alexander', 'Patrick', 'Jack', 'Dennis', 'Jerry',
    ];

    private array $lastNames = [
        'Ochieng', 'Kamau', 'Mwangi', 'Kipchoge', 'Wanjiku', 'Otieno', 'Njoroge', 'Kimani', 'Odhiambo', 'Mutua',
        'Wekesa', 'Chebet', 'Kosgei', 'Rotich', 'Kiptoo', 'Langat', 'Kibet', 'Cherono', 'Tanui', 'Lagat',
        'Karanja', 'Gitau', 'Ngugi', 'Mburu', 'Nyambura', 'Wairimu', 'Gathoni', 'Muthoni', 'Njeri', 'Wambui',
        'Akinyi', 'Adhiambo', 'Anyango', 'Atieno', 'Awuor', 'Apiyo', 'Auma', 'Akoth', 'Awino', 'Aoko',
        'Barasa', 'Chege', 'Gacheru', 'Irungu', 'Juma', 'Kariuki', 'Kinyanjui', 'Macharia', 'Maingi', 'Mugo',
    ];

    private array $nicknames = [
        'The Shark', 'Silky', 'The Machine', 'Flash', 'Ice Man', 'The Magician', 'Thunder', 'The Natural',
        'Rocket', 'The Warrior', 'Smooth', 'The Professor', 'Dynamite', 'The Wizard', 'Lightning', 'The Captain',
        'Cobra', 'The Boss', 'Fury', 'The Ace', 'Slick', 'The Master', 'Storm', 'The Legend', 'Blaze', 'The King',
        'Viper', 'The General', 'Hawk', 'The Phantom', 'Swift', 'The Sniper', 'Eagle', 'The Hammer', 'Wolf', 'The Doctor',
        'Panther', 'The Duke', 'Tiger', 'The Assassin', 'Ghost', 'The Surgeon', 'Phoenix', 'The Gladiator', 'Ninja', 'The Hunter',
        'Dragon', 'The Titan', 'Maverick', 'The Champion',
    ];

    public function run(): void
    {
        $this->command->info('Creating 100 test players...');

        // Get Kenya country
        $kenya = GeographicUnit::where('code', 'KE')
            ->where('level', GeographicLevel::NATIONAL->value)
            ->first();

        // Get atomic level units for variety
        $communities = GeographicUnit::where('level', GeographicLevel::ATOMIC->value)
            ->where('country_code', 'KE')
            ->limit(20)
            ->get();

        if ($communities->isEmpty()) {
            $this->command->error('No atomic geographic units found. Run GeographicUnitSeeder first.');
            return;
        }

        $players = [];
        $usedNicknames = [];

        for ($i = 1; $i <= 100; $i++) {
            $firstName = $this->firstNames[array_rand($this->firstNames)];
            $lastName = $this->lastNames[array_rand($this->lastNames)];

            // Get unique nickname
            do {
                $nickname = $this->nicknames[array_rand($this->nicknames)] . ' ' . rand(1, 99);
            } while (in_array($nickname, $usedNicknames));
            $usedNicknames[] = $nickname;

            $phoneNumber = '+2547' . str_pad((10000000 + $i), 8, '0', STR_PAD_LEFT);

            // Create user
            $user = User::create([
                'phone_number' => $phoneNumber,
                'email' => "player{$i}@test.cuesports.africa",
                'password' => Hash::make('password'),
                'country_id' => $kenya?->id,
                'is_player' => true,
                'is_active' => true,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]);

            // Create player profile with varied ratings (800-1400)
            $rating = rand(800, 1400);
            $profile = PlayerProfile::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'nickname' => $nickname,
                'date_of_birth' => now()->subYears(rand(18, 45))->subDays(rand(0, 365)),
                'gender' => rand(0, 1) ? Gender::MALE : Gender::FEMALE,
                'geographic_unit_id' => $communities->random()->id,
                'rating' => $rating,
                'best_rating' => $rating + rand(0, 100),
            ]);

            $players[] = $profile;

            if ($i % 25 === 0) {
                $this->command->info("Created {$i} players...");
            }
        }

        $this->command->info('All 100 players created!');

        // Create tournament
        $this->command->info('Creating test tournament...');

        $tournament = Tournament::create([
            'name' => 'Kenya National Championship 2026',
            'description' => 'The biggest cue sports tournament in Kenya with 100 players competing for the national title.',
            'type' => TournamentType::REGULAR,
            'status' => TournamentStatus::REGISTRATION,
            'format' => TournamentFormat::KNOCKOUT,
            'geographic_scope_id' => $kenya?->id,
            'registration_opens_at' => now()->subDays(7),
            'registration_closes_at' => now()->addDays(1),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(9),
            'best_of' => 3,
            'confirmation_hours' => 24,
            'created_by' => User::where('is_super_admin', true)->first()?->id ?? 1,
        ]);

        $this->command->info("Tournament created: {$tournament->name} (ID: {$tournament->id})");

        // Register all players
        $this->command->info('Registering all 100 players to tournament...');

        foreach ($players as $index => $profile) {
            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'player_profile_id' => $profile->id,
                'status' => ParticipantStatus::REGISTERED,
                'registered_at' => now(),
            ]);

            if (($index + 1) % 25 === 0) {
                $this->command->info("Registered " . ($index + 1) . " players...");
            }
        }

        // Update participant count
        $tournament->update(['participants_count' => 100]);

        $this->command->info('All 100 players registered!');
        $this->command->info("Tournament ID: {$tournament->id}");
        $this->command->info('You can now start the tournament and generate brackets.');
    }
}
