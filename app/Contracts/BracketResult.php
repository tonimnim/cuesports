<?php

namespace App\Contracts;

/**
 * Value object representing the result of bracket generation.
 *
 * Immutable object containing metadata about the generated bracket.
 */
readonly class BracketResult
{
    public function __construct(
        public int $bracketSize,
        public int $totalRounds,
        public int $byeCount,
        public int $matchesCreated,
        public int $byeMatchesProcessed,
        public array $roundStructure = [],
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            bracketSize: $data['bracket_size'] ?? 0,
            totalRounds: $data['total_rounds'] ?? 0,
            byeCount: $data['bye_count'] ?? 0,
            matchesCreated: $data['matches_created'] ?? 0,
            byeMatchesProcessed: $data['bye_matches_processed'] ?? 0,
            roundStructure: $data['round_structure'] ?? [],
        );
    }

    /**
     * Convert to array for logging/storage.
     */
    public function toArray(): array
    {
        return [
            'bracket_size' => $this->bracketSize,
            'total_rounds' => $this->totalRounds,
            'bye_count' => $this->byeCount,
            'matches_created' => $this->matchesCreated,
            'bye_matches_processed' => $this->byeMatchesProcessed,
            'round_structure' => $this->roundStructure,
        ];
    }
}
