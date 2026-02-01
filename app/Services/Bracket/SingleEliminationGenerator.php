<?php

namespace App\Services\Bracket;

use App\Contracts\BracketGeneratorInterface;
use App\Contracts\BracketResult;
use App\Contracts\SeedAssignment;
use App\Contracts\SeederInterface;
use App\Enums\MatchStatus;
use App\Enums\TournamentFormat;
use App\Models\GameMatch;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single Elimination Bracket Generator.
 *
 * Generates a standard single elimination bracket where:
 * - Players are eliminated after one loss
 * - BYEs are given to top seeds when bracket isn't full
 * - Higher seeds face lower seeds in early rounds
 *
 * Algorithm:
 * 1. Seed participants by rating (highest = seed 1)
 * 2. Calculate bracket size (next power of 2)
 * 3. Create ALL matches for ALL rounds first (empty placeholders)
 * 4. Populate Round 1 only with seeded players
 * 5. Process BYE matches (advance players with no opponent)
 *
 * Key principle: BYEs are processed AFTER match creation, not during.
 * This prevents cascading BYEs from corrupting the bracket structure.
 */
class SingleEliminationGenerator implements BracketGeneratorInterface
{
    /**
     * Minimum participants for a valid tournament.
     */
    protected const MIN_PARTICIPANTS = 2;

    /**
     * @var Collection<int, GameMatch> Created matches indexed by "round:position"
     */
    protected Collection $matchMap;

    public function __construct(
        protected SeederInterface $seeder,
        protected BracketStructureBuilder $structureBuilder,
        protected ByeProcessor $byeProcessor,
    ) {
        $this->matchMap = collect();
    }

    /**
     * {@inheritdoc}
     */
    public function generate(Tournament $tournament): BracketResult
    {
        $participants = $this->seeder->seed($tournament);
        $participantCount = $participants->count();

        if ($participantCount < self::MIN_PARTICIPANTS) {
            throw new \InvalidArgumentException(
                "Tournament requires at least " . self::MIN_PARTICIPANTS . " participants, got {$participantCount}"
            );
        }

        $bracketSize = $this->structureBuilder->calculateBracketSize($participantCount);
        $totalRounds = $this->structureBuilder->calculateTotalRounds($bracketSize);
        $byeCount = $this->structureBuilder->calculateByeCount($bracketSize, $participantCount);

        // Calculate expected matches: sum of matches per round
        $expectedMatches = 0;
        $matchesInRound = $bracketSize / 2;
        for ($r = 1; $r <= $totalRounds; $r++) {
            $expectedMatches += $matchesInRound;
            $matchesInRound = (int)($matchesInRound / 2);
        }

        Log::info("=== BRACKET GENERATION START ===", [
            'tournament_id' => $tournament->id,
            'tournament_name' => $tournament->name,
            'participants' => $participantCount,
            'bracket_size' => $bracketSize,
            'total_rounds' => $totalRounds,
            'bye_count' => $byeCount,
            'expected_matches' => $expectedMatches,
        ]);

        // Reset match map for this generation
        $this->matchMap = collect();
        $roundStructure = [];

        DB::transaction(function () use ($tournament, $participants, $bracketSize, $totalRounds, &$roundStructure) {
            // Step 1: Create all matches for all rounds (empty placeholders)
            $roundStructure = $this->createAllRounds($tournament, $bracketSize, $totalRounds);
            Log::info("Step 1 complete: Created matches", ['match_count' => $this->matchMap->count()]);

            // Step 2: Link matches to their next round matches
            $this->linkMatches($totalRounds);
            Log::info("Step 2 complete: Linked matches");

            // Step 3: Populate Round 1 with seeded players
            $this->populateFirstRound($participants, $bracketSize);
            Log::info("Step 3 complete: Populated Round 1");
        });

        // Step 4: Process BYE matches (outside transaction for clarity in logging)
        $round1Matches = $this->getMatchesForRound(1);
        Log::info("Step 4: Processing BYEs", ['round1_match_count' => $round1Matches->count()]);

        $byeMatchesProcessed = $this->byeProcessor->processAll($round1Matches);

        // Step 5: Create 3rd place match (if we have semi-finals)
        $thirdPlaceMatchCreated = false;
        if ($totalRounds >= 2) {
            $this->createThirdPlaceMatch($tournament, $totalRounds);
            $thirdPlaceMatchCreated = true;
            Log::info("Step 5: Created 3rd place match");
        }

        // Log final state of all matches
        Log::info("=== BRACKET GENERATION COMPLETE ===", [
            'tournament_id' => $tournament->id,
            'matches_created' => $this->matchMap->count(),
            'byes_processed' => $byeMatchesProcessed,
            'third_place_match' => $thirdPlaceMatchCreated,
        ]);

        // Log each match state
        foreach ($this->matchMap as $key => $match) {
            $match->refresh();
            Log::info("Match state: {$key}", [
                'id' => $match->id,
                'round' => $match->round_number,
                'position' => $match->bracket_position,
                'round_name' => $match->round_name,
                'p1' => $match->player1_id,
                'p2' => $match->player2_id,
                'status' => $match->status->value,
                'type' => $match->match_type?->value,
                'winner' => $match->winner_id,
            ]);
        }

        return new BracketResult(
            bracketSize: $bracketSize,
            totalRounds: $totalRounds,
            byeCount: $byeCount,
            matchesCreated: $this->matchMap->count(),
            byeMatchesProcessed: $byeMatchesProcessed,
            roundStructure: $roundStructure,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Tournament $tournament): bool
    {
        return $tournament->format === TournamentFormat::KNOCKOUT;
    }

    /**
     * {@inheritdoc}
     */
    public function getMinimumParticipants(): int
    {
        return self::MIN_PARTICIPANTS;
    }

    /**
     * {@inheritdoc}
     */
    public function advanceWinner(GameMatch $completedMatch): void
    {
        if (!$completedMatch->winner_id) {
            Log::warning("Cannot advance: match {$completedMatch->id} has no winner");
            return;
        }

        if (!$completedMatch->next_match_id) {
            Log::info("Match {$completedMatch->id} is the final - no next match");
            return;
        }

        $nextMatch = GameMatch::find($completedMatch->next_match_id);
        if (!$nextMatch) {
            Log::error("Next match {$completedMatch->next_match_id} not found");
            return;
        }

        $slot = $completedMatch->next_match_slot === 'player1' ? 'player1_id' : 'player2_id';
        $nextMatch->update([$slot => $completedMatch->winner_id]);

        Log::info("Advanced player {$completedMatch->winner_id} to match {$nextMatch->id} as {$slot}");

        // Check if next match became a BYE (other player already there via BYE)
        $nextMatch->refresh();
        if ($this->byeProcessor->isByeMatch($nextMatch) && $nextMatch->status === MatchStatus::SCHEDULED) {
            $this->byeProcessor->processBye($nextMatch);
        }
    }

    /**
     * Create all matches for all rounds.
     *
     * @return array<int, array{name: string, matches: int}>
     */
    protected function createAllRounds(Tournament $tournament, int $bracketSize, int $totalRounds): array
    {
        $roundStructure = [];
        $matchesInRound = $bracketSize / 2;

        for ($round = 1; $round <= $totalRounds; $round++) {
            $roundName = $this->structureBuilder->getRoundName($matchesInRound, $round, $totalRounds);

            $roundStructure[$round] = [
                'name' => $roundName,
                'matches' => $matchesInRound,
            ];

            $this->createMatchesForRound($tournament, $round, $matchesInRound, $roundName);

            $matchesInRound = (int) ($matchesInRound / 2);
        }

        return $roundStructure;
    }

    /**
     * Create matches for a single round.
     */
    protected function createMatchesForRound(
        Tournament $tournament,
        int $round,
        int $matchCount,
        string $roundName
    ): void {
        $expiresAt = $tournament->starts_at?->addDays(3) ?? now()->addDays(3);

        for ($position = 0; $position < $matchCount; $position++) {
            $match = GameMatch::create([
                'tournament_id' => $tournament->id,
                'round_number' => $round,
                'round_name' => $roundName,
                'bracket_position' => $position,
                'match_type' => $this->getMatchTypeForRound($round, $this->structureBuilder->calculateTotalRounds(
                    $this->structureBuilder->calculateBracketSize($tournament->participants()->count())
                )),
                'status' => MatchStatus::SCHEDULED,
                'player1_id' => null,
                'player2_id' => null,
                'expires_at' => $expiresAt,
            ]);

            $this->matchMap->put("{$round}:{$position}", $match);
        }

        Log::debug("Created {$matchCount} matches for round {$round} ({$roundName})");
    }

    /**
     * Get match type based on round position.
     */
    protected function getMatchTypeForRound(int $round, int $totalRounds): \App\Enums\MatchType
    {
        $roundsFromFinal = $totalRounds - $round;

        return match ($roundsFromFinal) {
            0 => \App\Enums\MatchType::FINAL,
            1 => \App\Enums\MatchType::SEMI_FINAL,
            2 => \App\Enums\MatchType::QUARTER_FINAL,
            default => \App\Enums\MatchType::REGULAR,
        };
    }

    /**
     * Link matches to their next round matches.
     */
    protected function linkMatches(int $totalRounds): void
    {
        for ($round = 1; $round < $totalRounds; $round++) {
            $matchesInRound = $this->getMatchesForRound($round);

            foreach ($matchesInRound as $match) {
                $nextInfo = $this->structureBuilder->getNextMatchInfo($match->bracket_position);
                $nextMatch = $this->matchMap->get(($round + 1) . ":" . $nextInfo['position']);

                if ($nextMatch) {
                    $match->update([
                        'next_match_id' => $nextMatch->id,
                        'next_match_slot' => $nextInfo['slot'],
                    ]);
                }
            }
        }

        Log::debug("Linked matches across {$totalRounds} rounds");
    }

    /**
     * Populate Round 1 with seeded players.
     *
     * Uses TournamentParticipant IDs (not PlayerProfile IDs) as the
     * player1_id and player2_id columns reference tournament_participants.
     *
     * @param Collection<int, SeedAssignment> $seededParticipants
     */
    protected function populateFirstRound(Collection $seededParticipants, int $bracketSize): void
    {
        $positions = $this->structureBuilder->buildPositions($seededParticipants, $bracketSize);
        $round1Matches = $this->getMatchesForRound(1);

        foreach ($round1Matches as $match) {
            $position1 = $match->bracket_position * 2;
            $position2 = $match->bracket_position * 2 + 1;

            $player1 = $positions[$position1] ?? null;
            $player2 = $positions[$position2] ?? null;

            // Use participant->id (TournamentParticipant ID), not player_profile_id
            $match->update([
                'player1_id' => $player1?->participant->id,
                'player2_id' => $player2?->participant->id,
            ]);

            if ($player1 || $player2) {
                Log::debug("Match {$match->id}: {$player1?->getDisplayName()} vs {$player2?->getDisplayName()}");
            }
        }
    }

    /**
     * Get matches for a specific round.
     *
     * @return Collection<int, GameMatch>
     */
    protected function getMatchesForRound(int $round): Collection
    {
        return $this->matchMap->filter(function ($match, $key) use ($round) {
            return str_starts_with($key, "{$round}:");
        })->values();
    }

    /**
     * Create the 3rd place match.
     *
     * This match is played between the two semi-final losers.
     * It's created with the same round_number as the final but
     * with bracket_position = 1 (final is 0) and match_type = THIRD_PLACE.
     */
    protected function createThirdPlaceMatch(Tournament $tournament, int $totalRounds): void
    {
        $expiresAt = $tournament->starts_at?->addDays(3) ?? now()->addDays(3);

        $match = GameMatch::create([
            'tournament_id' => $tournament->id,
            'round_number' => $totalRounds,
            'round_name' => 'Third Place',
            'bracket_position' => 1, // Final is position 0
            'match_type' => \App\Enums\MatchType::THIRD_PLACE,
            'status' => MatchStatus::SCHEDULED,
            'player1_id' => null,
            'player2_id' => null,
            'next_match_id' => null, // No next match
            'next_match_slot' => null,
            'expires_at' => $expiresAt,
        ]);

        $this->matchMap->put("3rd_place", $match);
    }

    /**
     * Calculate match number (for display).
     */
    protected function calculateMatchNumber(int $round, int $position, int $matchesInRound): int
    {
        $previousRoundsMatches = 0;
        $currentRoundMatches = $matchesInRound * 2;

        for ($r = 1; $r < $round; $r++) {
            $previousRoundsMatches += $currentRoundMatches;
            $currentRoundMatches = (int) ($currentRoundMatches / 2);
        }

        return $previousRoundsMatches + $position + 1;
    }

    /**
     * Get race-to value for a round.
     *
     * Can be customized per tournament (finals often have longer races).
     */
    protected function getRaceToForRound(Tournament $tournament, int $round): int
    {
        // Use finals_race_to for the final round if set
        $totalRounds = $this->structureBuilder->calculateTotalRounds(
            $this->structureBuilder->calculateBracketSize($tournament->participants()->count())
        );

        if ($round === $totalRounds && $tournament->finals_race_to) {
            return $tournament->finals_race_to;
        }

        return $tournament->race_to ?? 5;
    }
}
