<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tournament Stages - for Special tournaments
        // Each stage represents a geographic level (ATOMIC → NANO → MICRO → MESO → MACRO → NATIONAL)
        Schema::create('tournament_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');

            // Geographic level this stage represents
            $table->unsignedTinyInteger('geographic_level');  // 1=ROOT, 2=NATIONAL, ... 7=ATOMIC
            $table->string('level_name');                     // "Community", "Ward", "Constituency", etc.

            // Stage order (1 = first stage, played at lowest level)
            $table->unsignedTinyInteger('stage_order');

            // Status
            $table->string('status')->default('pending');  // pending, active, completed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Stats
            $table->unsignedInteger('participants_count')->default(0);
            $table->unsignedInteger('matches_count')->default(0);
            $table->unsignedInteger('completed_matches_count')->default(0);

            $table->timestamps();

            // Indexes
            $table->unique(['tournament_id', 'geographic_level']);
            $table->index(['tournament_id', 'status']);
            $table->index('stage_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_stages');
    }
};
