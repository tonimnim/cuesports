<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use App\Models\GeographicUnit;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $kenya = GeographicUnit::where('code', 'KE')->first();
        $countryId = $kenya?->id;

        if (!User::where('email', 'admin@cuesports.africa')->exists()) {
            User::create([
                'phone_number' => '+254700000001',
                'email' => 'admin@cuesports.africa',
                'password' => Hash::make('password'),
                'country_id' => $countryId,
                'is_super_admin' => true,
                'is_support' => true,
                'is_player' => false,
                'is_organizer' => false,
                'is_active' => true,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]);
        }

        if (!User::where('email', 'support@cuesports.africa')->exists()) {
            User::create([
                'phone_number' => '+254700000003',
                'email' => 'support@cuesports.africa',
                'password' => Hash::make('password'),
                'country_id' => $countryId,
                'is_super_admin' => false,
                'is_support' => true,
                'is_player' => false,
                'is_organizer' => false,
                'is_active' => true,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Don't delete users on rollback to avoid data loss
    }
};
