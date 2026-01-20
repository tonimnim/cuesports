<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('player_profile_id')->constrained('player_profiles')->onDelete('cascade');

            // For seeding/bracket position
            $table->unsignedInteger('seed')->nullable();

            // Status in tournament
            $table->string('status')->default('registered');  // registered, active, eliminated, disqualified, winner

            // Current position in Special tournaments (which geographic level they're competing at)
            $table->foreignId('current_stage_id')->nullable()->constrained('tournament_stages');

            // Final position (1st, 2nd, 3rd, etc.) - set when tournament completes or player is eliminated
            $table->unsignedInteger('final_position')->nullable();

            // Tournament Stats (accumulated during tournament)
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('matches_won')->default(0);
            $table->unsignedInteger('matches_lost')->default(0);
            $table->unsignedInteger('frames_won')->default(0);
            $table->unsignedInteger('frames_lost')->default(0);
            $table->integer('frame_difference')->default(0);  // Can be negative
            $table->unsignedInteger('points')->default(0);    // = frames_won

            // Group stage info (for groups_knockout format)
            $table->unsignedTinyInteger('group_number')->nullable();
            $table->unsignedTinyInteger('group_position')->nullable();  // Position in group after group stage

            // Timestamps
            $table->timestamp('registered_at');
            $table->timestamp('eliminated_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['tournament_id', 'player_profile_id']);
            $table->index(['tournament_id', 'status']);
            $table->index(['tournament_id', 'group_number']);
            $table->index(['tournament_id', 'seed']);
            $table->index(['tournament_id', 'points', 'frame_difference']);  // For ranking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_participants');
    }
};
