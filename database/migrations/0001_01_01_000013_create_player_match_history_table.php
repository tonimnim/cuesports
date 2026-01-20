<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_match_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');

            // Opponent info (denormalized for fast queries)
            $table->foreignId('opponent_profile_id')->nullable()->constrained('player_profiles')->onDelete('set null');
            $table->string('opponent_name');  // Cached in case opponent is deleted
            $table->unsignedInteger('opponent_rating_at_time');

            // Match result
            $table->boolean('won');
            $table->boolean('is_bye')->default(false);
            $table->unsignedTinyInteger('frames_won');
            $table->unsignedTinyInteger('frames_lost');
            $table->string('score');  // e.g., "2-1", "2-0"

            // Player's rating at time of match
            $table->unsignedInteger('rating_before');
            $table->unsignedInteger('rating_after');
            $table->integer('rating_change');

            // Tournament context (denormalized)
            $table->string('tournament_name');
            $table->string('tournament_type');  // regular, special
            $table->string('match_type');  // regular, quarter_final, semi_final, final, third_place, group
            $table->unsignedTinyInteger('round_number');
            $table->string('round_name');

            // Geographic context (for Special tournaments)
            $table->foreignId('geographic_unit_id')->nullable()->constrained('geographic_units')->onDelete('set null');
            $table->string('geographic_level')->nullable();

            // Timestamps
            $table->timestamp('played_at');
            $table->timestamps();

            // Indexes for common queries
            $table->index('player_profile_id');
            $table->index(['player_profile_id', 'played_at']);
            $table->index(['player_profile_id', 'won']);
            $table->index(['player_profile_id', 'tournament_id']);
            $table->index(['player_profile_id', 'match_type']);
            $table->index(['player_profile_id', 'tournament_type']);
            $table->index('opponent_profile_id');

            // Unique constraint - one record per player per match
            $table->unique(['player_profile_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_match_history');
    }
};
