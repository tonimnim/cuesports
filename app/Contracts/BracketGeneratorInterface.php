<?php

namespace App\Contracts;

use App\Models\Tournament;

/**
 * Contract for bracket generation strategies.
 *
 * Different tournament formats (single elimination, double elimination, round robin)
 * implement this interface to provide their specific bracket generation logic.
 */
interface BracketGeneratorInterface
{
    /**
     * Generate the bracket structure for a tournament.
     *
     * @param Tournament $tournament The tournament to generate bracket for
     * @return BracketResult Result containing bracket metadata
     * @throws \InvalidArgumentException If tournament cannot have bracket generated
     */
    public function generate(Tournament $tournament): BracketResult;

    /**
     * Check if this generator supports the given tournament format.
     *
     * @param Tournament $tournament
     * @return bool
     */
    public function supports(Tournament $tournament): bool;

    /**
     * Get the minimum number of participants required.
     *
     * @return int
     */
    public function getMinimumParticipants(): int;

    /**
     * Advance a winner to their next match after completion.
     *
     * @param \App\Models\GameMatch $completedMatch
     * @return void
     */
    public function advanceWinner(\App\Models\GameMatch $completedMatch): void;
}
