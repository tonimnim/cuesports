<?php

use App\Enums\GeographicLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tournament_level_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('geographic_level')->unique();
            $table->unsignedTinyInteger('race_to')->default(3);
            $table->unsignedTinyInteger('finals_race_to')->nullable();
            $table->unsignedTinyInteger('confirmation_hours')->default(24);
            $table->timestamps();
        });

        // Seed default values for each geographic level
        $defaults = [
            GeographicLevel::ROOT->value => ['race_to' => 5, 'finals_race_to' => 6],      // Continental
            GeographicLevel::NATIONAL->value => ['race_to' => 4, 'finals_race_to' => 5],  // National
            GeographicLevel::MACRO->value => ['race_to' => 3, 'finals_race_to' => 4],     // Regional
            GeographicLevel::MESO->value => ['race_to' => 3, 'finals_race_to' => 4],      // County/District
            GeographicLevel::MICRO->value => ['race_to' => 2, 'finals_race_to' => 3],     // Constituency
            GeographicLevel::NANO->value => ['race_to' => 2, 'finals_race_to' => 3],      // Ward
            GeographicLevel::ATOMIC->value => ['race_to' => 2, 'finals_race_to' => 3],    // Community
        ];

        foreach ($defaults as $level => $settings) {
            DB::table('tournament_level_settings')->insert([
                'geographic_level' => $level,
                'race_to' => $settings['race_to'],
                'finals_race_to' => $settings['finals_race_to'],
                'confirmation_hours' => 24,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_level_settings');
    }
};
