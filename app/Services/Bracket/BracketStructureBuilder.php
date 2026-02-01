<?php

namespace App\Services\Bracket;

use App\Contracts\SeedAssignment;
use Illuminate\Support\Collection;

/**
 * Builds the bracket structure (positions and matchups).
 *
 * Handles the mathematical aspects of bracket generation:
 * - Calculating bracket size (next power of 2)
 * - Standard tournament seeding positions
 * - Fair match seeding positions
 * - Determining BYE positions
 *
 * Supports two seeding modes:
 * - STANDARD: Traditional (1 vs 16, 2 vs 15) - top seeds meet in finals
 * - FAIR: Similar skills (1 vs 2, 3 vs 4) - fair Round 1 matchups
 */
class BracketStructureBuilder
{
    /**
     * Seeding mode constants.
     */
    public const MODE_STANDARD = 'standard';
    public const MODE_FAIR = 'fair';

    /**
     * Current seeding mode.
     */
    protected string $seedingMode = self::MODE_FAIR;

    /**
     * Standard seeding positions for brackets up to 16 players.
     * Key = seed number, Value = bracket position (0-indexed)
     *
     * This ensures:
     * - Seed 1 and Seed 2 are on opposite ends (meet only in final)
     * - Seeds 3-4 are placed to meet Seeds 1-2 in semis
     * - Lower seeds face higher seeds in early rounds
     */
    protected const SEED_POSITIONS = [
        2 => [1 => 0, 2 => 1],
        4 => [1 => 0, 2 => 3, 3 => 2, 4 => 1],
        8 => [1 => 0, 2 => 7, 3 => 4, 4 => 3, 5 => 2, 6 => 5, 7 => 6, 8 => 1],
        16 => [
            1 => 0, 2 => 15, 3 => 8, 4 => 7,
            5 => 4, 6 => 11, 7 => 12, 8 => 3,
            9 => 2, 10 => 13, 11 => 10, 12 => 5,
            13 => 6, 14 => 9, 15 => 14, 16 => 1,
        ],
    ];

    /**
     * Set the seeding mode.
     */
    public function setSeedingMode(string $mode): self
    {
        $this->seedingMode = $mode;
        return $this;
    }

    /**
     * Get the current seeding mode.
     */
    public function getSeedingMode(): string
    {
        return $this->seedingMode;
    }

    /**
     * Calculate the bracket size (next power of 2).
     */
    public function calculateBracketSize(int $participantCount): int
    {
        if ($participantCount < 2) {
            throw new \InvalidArgumentException('Need at least 2 participants');
        }

        $size = 2;
        while ($size < $participantCount) {
            $size *= 2;
        }

        return $size;
    }

    /**
     * Calculate total rounds needed for bracket size.
     */
    public function calculateTotalRounds(int $bracketSize): int
    {
        return (int) log($bracketSize, 2);
    }

    /**
     * Calculate number of BYEs needed.
     */
    public function calculateByeCount(int $bracketSize, int $participantCount): int
    {
        return $bracketSize - $participantCount;
    }

    /**
     * Build bracket positions array.
     *
     * Returns an array where index = bracket position, value = SeedAssignment or null (BYE).
     *
     * For FAIR mode with BYEs:
     * - Top seeds receive BYEs (reward for higher rating)
     * - Remaining seeds play each other (similar skills)
     *
     * @param Collection<int, SeedAssignment> $seededParticipants
     * @param int $bracketSize
     * @return array<int, SeedAssignment|null>
     */
    public function buildPositions(Collection $seededParticipants, int $bracketSize): array
    {
        $positions = array_fill(0, $bracketSize, null);
        $participantCount = $seededParticipants->count();
        $byeCount = $bracketSize - $participantCount;

        // For fair mode with BYEs, use special positioning
        if ($this->seedingMode === self::MODE_FAIR && $byeCount > 0) {
            return $this->buildFairPositionsWithByes($seededParticipants, $bracketSize, $byeCount);
        }

        // Standard positioning
        $seedPositions = $this->getSeedPositions($bracketSize);

        foreach ($seededParticipants as $seedAssignment) {
            $position = $seedPositions[$seedAssignment->seed] ?? ($seedAssignment->seed - 1);

            if ($position < $bracketSize) {
                $positions[$position] = $seedAssignment;
            }
        }

        return $positions;
    }

    /**
     * Build fair positions when BYEs are needed.
     *
     * Strategy:
     * - Top B seeds get BYEs (placed to face null opponents)
     * - Remaining seeds play each other sequentially
     *
     * Example: 6 players, bracket 8, 2 BYEs
     * - Seed 1 at pos 0, BYE at pos 1 (Seed 1 advances)
     * - Seed 3 at pos 2, Seed 4 at pos 3 (3 vs 4)
     * - Seed 5 at pos 4, Seed 6 at pos 5 (5 vs 6)
     * - Seed 2 at pos 6, BYE at pos 7 (Seed 2 advances)
     *
     * @return array<int, SeedAssignment|null>
     */
    protected function buildFairPositionsWithByes(Collection $seededParticipants, int $bracketSize, int $byeCount): array
    {
        $positions = array_fill(0, $bracketSize, null);
        $matchCount = $bracketSize / 2;

        // Seeds that get BYEs (top seeds)
        $byeSeeds = range(1, $byeCount);

        // Build position map
        $positionMap = [];
        $currentPos = 0;

        for ($match = 0; $match < $matchCount; $match++) {
            $pos1 = $match * 2;
            $pos2 = $match * 2 + 1;

            if ($match < $byeCount) {
                // This match is a BYE match
                // Place a top seed in pos1, leave pos2 as null (BYE)
                // Alternate placing BYE seeds at top and bottom of bracket
                if ($match % 2 === 0) {
                    // Place at top
                    $byeSeed = $byeSeeds[$match];
                    $positionMap[$byeSeed] = $pos1;
                } else {
                    // Place at bottom (swap positions)
                    $byeSeed = $byeSeeds[$match];
                    // Find the last match position and place there
                    $bottomMatchIndex = $matchCount - 1 - (int)floor($match / 2);
                    $positionMap[$byeSeed] = $bottomMatchIndex * 2;
                }
            }
        }

        // Simpler approach: distribute BYEs evenly, give to top seeds
        $positionMap = $this->calculateFairByePositions($bracketSize, $byeCount);

        // Place participants in positions
        foreach ($seededParticipants as $seedAssignment) {
            $position = $positionMap[$seedAssignment->seed] ?? ($seedAssignment->seed - 1);

            if ($position < $bracketSize) {
                $positions[$position] = $seedAssignment;
            }
        }

        return $positions;
    }

    /**
     * Calculate positions for fair seeding with BYEs.
     *
     * Ensures:
     * 1. Top B seeds get BYEs (paired with null)
     * 2. BYEs are distributed at top and bottom of bracket
     * 3. Remaining players play similar-skilled opponents
     *
     * @return array<int, int> seed => position mapping
     */
    protected function calculateFairByePositions(int $bracketSize, int $byeCount): array
    {
        $positions = [];
        $matchCount = $bracketSize / 2;
        $playingSeeds = $bracketSize - $byeCount; // Seeds that actually play

        // BYE matches are at the edges (top and bottom of bracket)
        // Real matches are in the middle
        $byeMatchIndices = [];
        for ($i = 0; $i < $byeCount; $i++) {
            if ($i % 2 === 0) {
                // Top of bracket
                $byeMatchIndices[] = (int)floor($i / 2);
            } else {
                // Bottom of bracket
                $byeMatchIndices[] = $matchCount - 1 - (int)floor($i / 2);
            }
        }

        // Assign BYE seeds to BYE match positions
        // Top seeds (1, 2, 3...) get the BYEs
        for ($i = 0; $i < $byeCount; $i++) {
            $seed = $i + 1;
            $matchIndex = $byeMatchIndices[$i];
            // Place seed in first position of the match (opponent will be null)
            $positions[$seed] = $matchIndex * 2;
        }

        // Remaining seeds play each other
        // Find non-BYE match indices
        $realMatchIndices = [];
        for ($m = 0; $m < $matchCount; $m++) {
            if (!in_array($m, $byeMatchIndices)) {
                $realMatchIndices[] = $m;
            }
        }

        // Assign remaining seeds (byeCount+1 to playingSeeds) to real matches
        // Pair them sequentially: seed B+1 vs B+2, seed B+3 vs B+4, etc.
        $remainingSeed = $byeCount + 1;
        foreach ($realMatchIndices as $matchIndex) {
            $pos1 = $matchIndex * 2;
            $pos2 = $matchIndex * 2 + 1;

            if ($remainingSeed <= $playingSeeds) {
                $positions[$remainingSeed] = $pos1;
                $remainingSeed++;
            }
            if ($remainingSeed <= $playingSeeds) {
                $positions[$remainingSeed] = $pos2;
                $remainingSeed++;
            }
        }

        return $positions;
    }

    /**
     * Get round name based on matches remaining.
     */
    public function getRoundName(int $matchesInRound, int $round, int $totalRounds): string
    {
        $roundsFromFinal = $totalRounds - $round;

        return match ($roundsFromFinal) {
            0 => 'Final',
            1 => 'Semi-Finals',
            2 => 'Quarter-Finals',
            default => "Round of " . ($matchesInRound * 2),
        };
    }

    /**
     * Get seeding positions for a bracket size.
     *
     * Returns positions based on current seeding mode:
     * - STANDARD: Traditional tournament seeding (1 vs 16, 2 vs 15)
     * - FAIR: Adjacent seed pairing (1 vs 2, 3 vs 4)
     *
     * @return array<int, int> seed => position mapping
     */
    protected function getSeedPositions(int $bracketSize): array
    {
        // Fair mode: sequential positions (1→0, 2→1, 3→2...)
        // This pairs adjacent seeds: 1v2, 3v4, 5v6, etc.
        if ($this->seedingMode === self::MODE_FAIR) {
            return $this->generateFairSeedPositions($bracketSize);
        }

        // Standard mode: use predefined positions if available
        if (isset(self::SEED_POSITIONS[$bracketSize])) {
            return self::SEED_POSITIONS[$bracketSize];
        }

        // Generate positions for larger brackets (standard mode)
        return $this->generateSeedPositions($bracketSize);
    }

    /**
     * Generate fair seeding positions.
     *
     * Sequential positions ensure adjacent seeds play each other:
     * - Seed 1 at Position 0, Seed 2 at Position 1 → Match: 1 vs 2
     * - Seed 3 at Position 2, Seed 4 at Position 3 → Match: 3 vs 4
     * - etc.
     *
     * @return array<int, int> seed => position mapping
     */
    protected function generateFairSeedPositions(int $bracketSize): array
    {
        $positions = [];
        for ($seed = 1; $seed <= $bracketSize; $seed++) {
            $positions[$seed] = $seed - 1;
        }
        return $positions;
    }

    /**
     * Generate seeding positions algorithmically for large brackets.
     *
     * Uses the standard tournament seeding algorithm:
     * 1 vs N, 2 vs N-1, etc. with proper bracket placement.
     */
    protected function generateSeedPositions(int $bracketSize): array
    {
        $positions = [];

        // Start with the base case
        $positions[1] = 0;
        $positions[2] = 1;

        // Build up the bracket recursively
        $currentSize = 2;
        while ($currentSize < $bracketSize) {
            $nextSize = $currentSize * 2;
            $newPositions = [];

            foreach ($positions as $seed => $position) {
                // Current seed stays in position * 2
                $newPositions[$seed] = $position * 2;
                // Complementary seed goes to position * 2 + 1
                $complementSeed = $nextSize + 1 - $seed;
                $newPositions[$complementSeed] = $position * 2 + 1;
            }

            $positions = $newPositions;
            $currentSize = $nextSize;
        }

        ksort($positions);
        return $positions;
    }

    /**
     * Get the next match position and slot for a given match.
     *
     * @param int $roundMatchPosition Current match's position in its round
     * @return array{position: int, slot: string}
     */
    public function getNextMatchInfo(int $roundMatchPosition): array
    {
        return [
            'position' => (int) floor($roundMatchPosition / 2),
            'slot' => $roundMatchPosition % 2 === 0 ? 'player1' : 'player2',
        ];
    }
}
