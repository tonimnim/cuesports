<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_rating_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_profile_id')->constrained()->onDelete('cascade');

            // Rating change
            $table->unsignedInteger('old_rating');
            $table->unsignedInteger('new_rating');
            $table->integer('change');  // Can be negative

            // Context
            $table->string('reason');  // match_result, admin_adjustment, tournament_bonus, decay
            $table->foreignId('match_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('set null');

            $table->timestamps();

            // Indexes
            $table->index('player_profile_id');
            $table->index(['player_profile_id', 'created_at']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_rating_history');
    }
};
