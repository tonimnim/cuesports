<?php

namespace Database\Seeders;

use App\Models\GeographicUnit;
use App\Models\User;
use App\Models\PlayerProfile;
use App\Enums\GeographicLevel;
use App\Enums\Gender;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed geographic units first (required for users)
        $this->call([
            GeographicUnitSeeder::class,
            AgeCategorySeeder::class,
        ]);

        // Get Kenya for the test user
        $kenya = GeographicUnit::where('code', 'KE')
            ->where('level', GeographicLevel::NATIONAL->value)
            ->first();

        // Get an ATOMIC level unit for player profile
        $community = GeographicUnit::where('level', GeographicLevel::ATOMIC->value)
            ->where('country_code', 'KE')
            ->first();

        // Create Super Admin
        $admin = User::create([
            'phone_number' => '+254700000001',
            'email' => 'admin@cuesports.africa',
            'password' => bcrypt('password'),
            'country_id' => $kenya?->id,
            'is_super_admin' => true,
            'is_support' => false,
            'is_player' => false,
            'is_organizer' => false,
            'is_active' => true,
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        // Create Test Player
        $player = User::create([
            'phone_number' => '+254700000002',
            'email' => 'player@cuesports.africa',
            'password' => bcrypt('password'),
            'country_id' => $kenya?->id,
            'is_super_admin' => false,
            'is_support' => false,
            'is_player' => true,
            'is_organizer' => false,
            'is_active' => true,
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        // Create player profile for test player
        if ($community) {
            PlayerProfile::create([
                'user_id' => $player->id,
                'first_name' => 'John',
                'last_name' => 'Mwangi',
                'nickname' => 'The Terminator',
                'date_of_birth' => '1995-05-15',
                'gender' => Gender::MALE,
                'geographic_unit_id' => $community->id,
                'rating' => 1000,
            ]);
        }
    }
}
