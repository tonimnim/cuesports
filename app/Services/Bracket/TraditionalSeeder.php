<?php

namespace App\Services\Bracket;

use App\Contracts\SeedAssignment;
use App\Contracts\SeederInterface;
use App\Enums\ParticipantStatus;
use App\Enums\RatingCategory;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Traditional Tournament Seeder - Seeds by rating.
 *
 * Standard tournament seeding used by FIFA, ATP, Olympics:
 * - Highest rated player = Seed 1
 * - Top seeds get BYEs when bracket isn't full
 * - Top seeds meet in later rounds (via bracket positioning)
 *
 * Simple, predictable, rewards achievement.
 */
class TraditionalSeeder implements SeederInterface
{
    /**
     * {@inheritdoc}
     */
    public function seed(Tournament $tournament): Collection
    {
        $participants = $this->getEligibleParticipants($tournament);

        if ($participants->isEmpty()) {
            return collect();
        }

        // Sort by rating descending (highest rated = seed 1)
        $sorted = $participants->sortByDesc(
            fn($p) => $p->playerProfile->rating ?? 0
        )->values();

        Log::info("Traditional seeding for tournament {$tournament->id}", [
            'participants' => $sorted->count(),
        ]);

        // Assign seeds sequentially
        return $this->assignSeeds($sorted);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'traditional';
    }

    /**
     * Get participants eligible to be seeded.
     */
    protected function getEligibleParticipants(Tournament $tournament): Collection
    {
        return $tournament->participants()
            ->whereIn('status', [
                ParticipantStatus::REGISTERED,
                ParticipantStatus::ACTIVE,
            ])
            ->with('playerProfile')
            ->get();
    }

    /**
     * Assign sequential seeds to the ordered participants.
     *
     * @return Collection<int, SeedAssignment>
     */
    protected function assignSeeds(Collection $orderedParticipants): Collection
    {
        return $orderedParticipants->map(function ($participant, $index) {
            $seed = $index + 1;

            // Update the participant's seed in the database
            $participant->update(['seed' => $seed]);

            $rating = $participant->playerProfile->rating ?? 1000;
            $category = RatingCategory::fromRating($rating);

            Log::debug("Seed {$seed}: {$participant->playerProfile->display_name} " .
                "(Rating: {$rating}, {$category->value})");

            return new SeedAssignment(
                participant: $participant,
                seed: $seed,
                rating: $rating,
            );
        });
    }
}
