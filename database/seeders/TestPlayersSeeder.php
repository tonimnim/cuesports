<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\GeographicLevel;
use App\Enums\RatingCategory;
use App\Models\GeographicUnit;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestPlayersSeeder extends Seeder
{
    // Common Kenyan first names
    private array $maleFirstNames = [
        'James', 'John', 'Peter', 'Michael', 'David', 'Joseph', 'Daniel', 'Samuel',
        'Stephen', 'Paul', 'Patrick', 'Charles', 'George', 'Martin', 'Francis',
        'Dennis', 'Brian', 'Kevin', 'Alex', 'Victor', 'Emmanuel', 'Moses', 'Andrew',
        'Simon', 'Eric', 'Felix', 'Bernard', 'Kenneth', 'Nicholas', 'Robert',
        'Kelvin', 'Collins', 'Edwin', 'Timothy', 'Mark', 'Philip', 'Anthony',
        'Lawrence', 'Christopher', 'Geoffrey', 'Benjamin', 'Vincent', 'Henry',
        'Benard', 'Julius', 'William', 'Solomon', 'Richard', 'Evans', 'Erick',
        'Ezekiel', 'Boniface', 'Fredrick', 'Stanley', 'Job', 'Gilbert', 'Elijah',
        'Isaac', 'Samson', 'Josphat', 'Wycliff', 'Amos', 'Shadrack', 'Gideon',
        'Meshack', 'Justus', 'Raymond', 'Crispin', 'Antony', 'Jacob', 'Abraham',
    ];

    private array $femaleFirstNames = [
        'Mary', 'Jane', 'Grace', 'Faith', 'Ann', 'Joyce', 'Sarah', 'Elizabeth',
        'Catherine', 'Margaret', 'Susan', 'Alice', 'Dorothy', 'Mercy', 'Lucy',
        'Florence', 'Christine', 'Janet', 'Beatrice', 'Esther', 'Ruth', 'Irene',
        'Gladys', 'Rose', 'Charity', 'Eunice', 'Evelyn', 'Agnes', 'Carolyne',
        'Nancy', 'Pauline', 'Hannah', 'Diana', 'Judith', 'Martha', 'Rachel',
        'Priscilla', 'Monica', 'Josephine', 'Miriam', 'Doris', 'Lillian', 'Winnie',
        'Pamela', 'Patricia', 'Caroline', 'Stella', 'Rebecca', 'Sharon', 'Cynthia',
    ];

    // Common Kenyan last names (Kikuyu/Embu region common around Kirinyaga)
    private array $lastNames = [
        'Mwangi', 'Kamau', 'Njoroge', 'Wanjiku', 'Kariuki', 'Githinji', 'Maina',
        'Ngugi', 'Ndirangu', 'Kinyua', 'Mugo', 'Waweru', 'Karanja', 'Gichuki',
        'Mwaura', 'Kiama', 'Gitau', 'Ndungu', 'Mbugua', 'Kibathi', 'Waithaka',
        'Irungu', 'Macharia', 'Gacheru', 'Kiarie', 'Njuguna', 'Muturi', 'Thuku',
        'Njenga', 'Wambui', 'Mwanzia', 'Kihara', 'Nyaga', 'Mureithi', 'Mungai',
        'Kimani', 'Gitonga', 'Muthoni', 'Wanjau', 'Kahiga', 'Njiru', 'Munene',
        'Mburu', 'Muchiri', 'Njeri', 'Kinuthia', 'Kiragu', 'Githaiga', 'Gathoni',
        'Wachira', 'Kagwe', 'Mwai', 'Kang\'ethe', 'Kabiru', 'Nduati', 'Kibe',
        'Wahome', 'Gikonyo', 'Ngure', 'Murigi', 'Kago', 'Njuki', 'Gachihi',
    ];

    public function run(): void
    {
        // Find the Kutus community
        $kutus = GeographicUnit::where('code', 'KE-KRY-MW-KT-C1')->first();

        if (!$kutus) {
            $this->command->error('Kutus community not found. Please run GeographicUnitSeeder first.');
            return;
        }

        // Find Kenya for country_id
        $kenya = GeographicUnit::where('code', 'KE')
            ->where('level', GeographicLevel::NATIONAL->value)
            ->first();

        $this->command->info("Seeding 250 players in Kutus community...");

        $players = [];
        $usedPhoneNumbers = [];
        $usedEmails = [];
        $usedNationalIds = [];

        for ($i = 1; $i <= 250; $i++) {
            // 70% male, 30% female for pool community demographic
            $isMale = fake()->boolean(70);
            $gender = $isMale ? Gender::MALE : Gender::FEMALE;

            $firstName = $isMale
                ? fake()->randomElement($this->maleFirstNames)
                : fake()->randomElement($this->femaleFirstNames);
            $lastName = fake()->randomElement($this->lastNames);

            // Generate unique phone number (Kenyan format)
            do {
                $phoneNumber = '+2547' . fake()->randomElement(['0', '1', '2', '9']) . fake()->numerify('#######');
            } while (in_array($phoneNumber, $usedPhoneNumbers));
            $usedPhoneNumbers[] = $phoneNumber;

            // Generate unique email
            do {
                $email = strtolower($firstName . '.' . $lastName . fake()->numerify('##')) . '@example.com';
            } while (in_array($email, $usedEmails));
            $usedEmails[] = $email;

            // Generate unique national ID (8 digits)
            do {
                $nationalId = fake()->numerify('########');
            } while (in_array($nationalId, $usedNationalIds));
            $usedNationalIds[] = $nationalId;

            // Rating distribution: Most beginners/intermediate, fewer advanced/pro
            $ratingWeight = fake()->randomFloat(2, 0, 1);
            $rating = match (true) {
                $ratingWeight < 0.40 => fake()->numberBetween(800, 1199),   // 40% beginner
                $ratingWeight < 0.75 => fake()->numberBetween(1200, 1599),  // 35% intermediate
                $ratingWeight < 0.92 => fake()->numberBetween(1600, 1999),  // 17% advanced
                default => fake()->numberBetween(2000, 2300),               // 8% pro
            };

            // Generate match history based on rating
            $totalMatches = fake()->numberBetween(5, 150);
            $winRate = match (true) {
                $rating < 1200 => fake()->randomFloat(2, 0.30, 0.45),
                $rating < 1600 => fake()->randomFloat(2, 0.45, 0.55),
                $rating < 2000 => fake()->randomFloat(2, 0.55, 0.65),
                default => fake()->randomFloat(2, 0.65, 0.80),
            };

            $wins = (int) round($totalMatches * $winRate);
            $losses = $totalMatches - $wins;

            // Frame statistics (race to 3 average)
            $avgFramesPerMatch = 4.5; // average frames in race to 3
            $totalFrames = (int) round($totalMatches * $avgFramesPerMatch);
            $framesWon = (int) round($totalFrames * ($winRate + fake()->randomFloat(2, -0.05, 0.05)));
            $framesLost = $totalFrames - $framesWon;

            // Tournament stats
            $tournamentsPlayed = fake()->numberBetween(0, min(20, (int) ($totalMatches / 5)));
            $tournamentsWon = $rating >= 1600 && $tournamentsPlayed > 0
                ? fake()->numberBetween(0, min(3, (int) ($tournamentsPlayed * 0.2)))
                : 0;

            // Age between 18 and 55
            $dateOfBirth = fake()->dateTimeBetween('-55 years', '-18 years');

            // Create user
            $user = User::create([
                'phone_number' => $phoneNumber,
                'email' => $email,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'password' => Hash::make('password123'),
                'country_id' => $kenya?->id,
                'is_super_admin' => false,
                'is_support' => false,
                'is_player' => true,
                'is_organizer' => false,
                'is_active' => true,
            ]);

            // Create player profile
            PlayerProfile::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'nickname' => fake()->boolean(30) ? fake()->word() . fake()->numerify('##') : null,
                'national_id_number' => $nationalId,
                'date_of_birth' => $dateOfBirth,
                'gender' => $gender,
                'geographic_unit_id' => $kutus->id,
                'country_id' => $kenya?->id,
                'rating' => $rating,
                'rating_category' => RatingCategory::fromRating($rating),
                'best_rating' => $rating + fake()->numberBetween(0, 100),
                'total_matches' => $totalMatches,
                'wins' => $wins,
                'losses' => $losses,
                'lifetime_frames_won' => $framesWon,
                'lifetime_frames_lost' => $framesLost,
                'lifetime_frame_difference' => $framesWon - $framesLost,
                'tournaments_played' => $tournamentsPlayed,
                'tournaments_won' => $tournamentsWon,
            ]);

            if ($i % 50 === 0) {
                $this->command->info("Created {$i} players...");
            }
        }

        $this->command->info("Successfully seeded 250 players in Kutus community!");

        // Display summary
        $summary = PlayerProfile::where('geographic_unit_id', $kutus->id)
            ->selectRaw('rating_category, COUNT(*) as count')
            ->groupBy('rating_category')
            ->get();

        $this->command->info("\nRating Distribution:");
        foreach ($summary as $row) {
            $category = RatingCategory::tryFrom($row->rating_category)?->label() ?? $row->rating_category;
            $this->command->info("  {$category}: {$row->count} players");
        }
    }
}
