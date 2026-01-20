<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class UpdateTournamentDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?Carbon $registrationOpensAt = null,
        public ?Carbon $registrationClosesAt = null,
        public ?Carbon $startsAt = null,
        public ?int $winnersCount = null,
        public ?int $bestOf = null,
        public ?int $confirmationHours = null,
        public ?int $minPlayersForGroups = null,
        public ?int $playersPerGroup = null,
        public ?int $advancePerGroup = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            registrationOpensAt: isset($data['registration_opens_at']) ? Carbon::parse($data['registration_opens_at']) : null,
            registrationClosesAt: isset($data['registration_closes_at']) ? Carbon::parse($data['registration_closes_at']) : null,
            startsAt: isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null,
            winnersCount: $data['winners_count'] ?? null,
            bestOf: $data['best_of'] ?? null,
            confirmationHours: $data['confirmation_hours'] ?? null,
            minPlayersForGroups: $data['min_players_for_groups'] ?? null,
            playersPerGroup: $data['players_per_group'] ?? null,
            advancePerGroup: $data['advance_per_group'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'registration_opens_at' => $this->registrationOpensAt,
            'registration_closes_at' => $this->registrationClosesAt,
            'starts_at' => $this->startsAt,
            'winners_count' => $this->winnersCount,
            'best_of' => $this->bestOf,
            'confirmation_hours' => $this->confirmationHours,
            'min_players_for_groups' => $this->minPlayersForGroups,
            'players_per_group' => $this->playersPerGroup,
            'advance_per_group' => $this->advancePerGroup,
        ], fn($value) => $value !== null);
    }

    public function hasChanges(): bool
    {
        return !empty($this->toArray());
    }
}
