<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('stage_id')->nullable()->constrained('tournament_stages')->onDelete('cascade');

            // Round information
            $table->unsignedTinyInteger('round_number');
            $table->string('round_name');
            $table->string('match_type');  // regular, quarter_final, semi_final, final, third_place, bye, group
            $table->unsignedInteger('bracket_position')->nullable();  // Position in the bracket for ordering

            // Players (using tournament_participants)
            $table->foreignId('player1_id')->nullable()->constrained('tournament_participants');
            $table->foreignId('player2_id')->nullable()->constrained('tournament_participants');

            // Scores (0, 1, or 2 for best of 3)
            $table->unsignedTinyInteger('player1_score')->default(0);
            $table->unsignedTinyInteger('player2_score')->default(0);

            // Result
            $table->foreignId('winner_id')->nullable()->constrained('tournament_participants');
            $table->foreignId('loser_id')->nullable()->constrained('tournament_participants');

            // Status
            $table->string('status')->default('scheduled');  // scheduled, pending_confirmation, completed, disputed, expired, cancelled

            // Submission tracking
            $table->foreignId('submitted_by')->nullable()->constrained('tournament_participants');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('tournament_participants');
            $table->timestamp('confirmed_at')->nullable();

            // Dispute tracking
            $table->foreignId('disputed_by')->nullable()->constrained('tournament_participants');
            $table->timestamp('disputed_at')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');  // Support user who resolved
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            // Scheduling
            $table->timestamp('scheduled_play_date')->nullable();  // Optional: when players agree to play
            $table->timestamp('played_at')->nullable();  // When the match was actually played
            $table->timestamp('expires_at');  // 3 days from generation

            // Bracket progression
            $table->foreignId('next_match_id')->nullable();  // Self-referencing, will add constraint after
            $table->string('next_match_slot')->nullable();  // 'player1' or 'player2'

            // For group stage
            $table->unsignedTinyInteger('group_number')->nullable();

            // For Special tournaments - geographic context
            $table->foreignId('geographic_unit_id')->nullable()->constrained('geographic_units');

            $table->timestamps();

            // Indexes
            $table->index(['tournament_id', 'status']);
            $table->index(['tournament_id', 'round_number']);
            $table->index(['tournament_id', 'match_type']);
            $table->index(['stage_id', 'status']);
            $table->index('expires_at');
            $table->index(['player1_id', 'status']);
            $table->index(['player2_id', 'status']);
        });

        // Add self-referencing foreign key for next_match_id
        Schema::table('matches', function (Blueprint $table) {
            $table->foreign('next_match_id')->references('id')->on('matches')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['next_match_id']);
        });

        Schema::dropIfExists('matches');
    }
};
