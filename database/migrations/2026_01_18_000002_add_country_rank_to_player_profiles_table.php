<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            // Store country ID directly for efficient ranking queries
            $table->foreignId('country_id')
                ->nullable()
                ->after('geographic_unit_id')
                ->constrained('geographic_units')
                ->nullOnDelete();

            // Country rank (position among all players in the same country)
            $table->unsignedInteger('country_rank')->nullable()->after('country_id');

            // Indexes for efficient ranking queries
            $table->index(['country_id', 'rating'], 'player_profiles_country_rating_idx');
            $table->index(['country_id', 'country_rank'], 'player_profiles_country_rank_idx');
        });
    }

    public function down(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->dropIndex('player_profiles_country_rating_idx');
            $table->dropIndex('player_profiles_country_rank_idx');
            $table->dropForeign(['country_id']);
            $table->dropColumn(['country_id', 'country_rank']);
        });
    }
};
