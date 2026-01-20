<?php

use App\Enums\Gender;
use App\Enums\RatingCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Identity
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nickname')->nullable();
            $table->string('national_id_number')->nullable();
            $table->string('photo_url')->nullable();

            // Demographics
            $table->date('date_of_birth');
            $table->string('gender');

            // Location (ATOMIC level - where player competes from)
            $table->foreignId('geographic_unit_id')->constrained('geographic_units');

            // Ratings & Stats
            $table->unsignedInteger('rating')->default(1000);
            $table->string('rating_category')->default(RatingCategory::BEGINNER->value);
            $table->unsignedInteger('best_rating')->default(1000);  // Highest rating ever achieved
            $table->unsignedInteger('total_matches')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);

            // Lifetime Frame Stats (aggregated across all tournaments)
            $table->unsignedInteger('lifetime_frames_won')->default(0);
            $table->unsignedInteger('lifetime_frames_lost')->default(0);
            $table->integer('lifetime_frame_difference')->default(0);  // Can be negative

            // Tournament Stats
            $table->unsignedInteger('tournaments_played')->default(0);
            $table->unsignedInteger('tournaments_won')->default(0);

            $table->timestamps();

            // Indexes
            $table->unique('user_id');
            $table->index('rating');
            $table->index('rating_category');
            $table->index('geographic_unit_id');
            $table->index(['gender', 'rating_category']);
            $table->index('best_rating');
            $table->index('lifetime_frame_difference');
            $table->index('tournaments_won');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_profiles');
    }
};
