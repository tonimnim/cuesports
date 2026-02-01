<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds optimized indexes for country rankings queries:
     * - Top 10 players per country (by rating)
     * - Pro player counts per country
     * - Aggregate stats per country
     */
    public function up(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            // Composite index for getting top players per country (covers ORDER BY rating DESC)
            // This is the most critical index for the Top 10 Average query
            $table->index(['country_id', 'rating'], 'idx_player_profiles_country_rating');

            // Composite index for counting players by category per country
            $table->index(['country_id', 'rating_category'], 'idx_player_profiles_country_category');

            // Composite index for aggregating tournaments won per country
            $table->index(['country_id', 'tournaments_won'], 'idx_player_profiles_country_tournaments');
        });

        // Add index on geographic_units for faster country lookups
        Schema::table('geographic_units', function (Blueprint $table) {
            // Index for filtering by level (to get only countries)
            $table->index('level', 'idx_geographic_units_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_player_profiles_country_rating');
            $table->dropIndex('idx_player_profiles_country_category');
            $table->dropIndex('idx_player_profiles_country_tournaments');
        });

        Schema::table('geographic_units', function (Blueprint $table) {
            $table->dropIndex('idx_geographic_units_level');
        });
    }
};
