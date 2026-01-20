<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Type & Status
            $table->string('type');          // regular, special
            $table->string('status')->default('draft');  // draft, registration, active, completed, cancelled
            $table->string('format');        // knockout, groups_knockout

            // Geographic Scope - determines WHO can participate
            // For Regular: players from this unit or below can join
            // For Special: players from this unit or below, progression follows hierarchy
            $table->foreignId('geographic_scope_id')->constrained('geographic_units');

            // Dates
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();

            // Winners Configuration
            $table->unsignedTinyInteger('winners_count')->default(3);      // How many winners to determine (1st, 2nd, 3rd, etc.)
            $table->unsignedTinyInteger('winners_per_level')->default(2);  // For Special: how many advance from each geographic level

            // Match Configuration
            $table->unsignedTinyInteger('best_of')->default(3);            // Best of 3 frames
            $table->unsignedTinyInteger('confirmation_hours')->default(24); // Hours to confirm match result

            // Group Stage Configuration (for groups_knockout format)
            $table->unsignedInteger('min_players_for_groups')->default(16);  // Use groups if players >= this number
            $table->unsignedTinyInteger('players_per_group')->default(4);    // Players in each group
            $table->unsignedTinyInteger('advance_per_group')->default(2);    // Top X from each group advance

            // Stats
            $table->unsignedInteger('participants_count')->default(0);
            $table->unsignedInteger('matches_count')->default(0);

            // Admin
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index('status');
            $table->index('geographic_scope_id');
            $table->index(['status', 'type']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
