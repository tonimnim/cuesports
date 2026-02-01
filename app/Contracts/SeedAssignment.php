<?php

namespace App\Contracts;

use App\Models\TournamentParticipant;

/**
 * Value object representing a seeded participant.
 *
 * Immutable object linking a participant to their seed number.
 */
readonly class SeedAssignment
{
    public function __construct(
        public TournamentParticipant $participant,
        public int $seed,
        public int $rating,
    ) {}

    /**
     * Get the participant's ID.
     */
    public function getParticipantId(): int
    {
        return $this->participant->id;
    }

    /**
     * Get the participant's display name.
     */
    public function getDisplayName(): string
    {
        return $this->participant->playerProfile->display_name ?? 'Unknown';
    }

    /**
     * Check if this is a top seed (1 or 2).
     */
    public function isTopSeed(): bool
    {
        return $this->seed <= 2;
    }
}
