<?php

namespace App\DTOs;

use App\Enums\TournamentFormat;
use App\Enums\TournamentType;
use Carbon\Carbon;

readonly class CreateTournamentDTO
{
    public function __construct(
        public string $name,
        public TournamentType $type,
        public TournamentFormat $format,
        public int $geographicScopeId,
        public Carbon $registrationClosesAt,
        public Carbon $startsAt,
        public ?string $description = null,
        public ?Carbon $registrationOpensAt = null,
        public int $winnersCount = 3,
        public int $winnersPerLevel = 2,
        public int $bestOf = 3,
        public int $confirmationHours = 24,
        public int $minPlayersForGroups = 16,
        public int $playersPerGroup = 4,
        public int $advancePerGroup = 2,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: TournamentType::from($data['type']),
            format: TournamentFormat::from($data['format']),
            geographicScopeId: $data['geographic_scope_id'],
            registrationClosesAt: Carbon::parse($data['registration_closes_at']),
            startsAt: Carbon::parse($data['starts_at']),
            description: $data['description'] ?? null,
            registrationOpensAt: isset($data['registration_opens_at']) ? Carbon::parse($data['registration_opens_at']) : null,
            winnersCount: $data['winners_count'] ?? 3,
            winnersPerLevel: $data['winners_per_level'] ?? 2,
            bestOf: $data['best_of'] ?? 3,
            confirmationHours: $data['confirmation_hours'] ?? 24,
            minPlayersForGroups: $data['min_players_for_groups'] ?? 16,
            playersPerGroup: $data['players_per_group'] ?? 4,
            advancePerGroup: $data['advance_per_group'] ?? 2,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'format' => $this->format->value,
            'geographic_scope_id' => $this->geographicScopeId,
            'registration_opens_at' => $this->registrationOpensAt,
            'registration_closes_at' => $this->registrationClosesAt,
            'starts_at' => $this->startsAt,
            'winners_count' => $this->winnersCount,
            'winners_per_level' => $this->winnersPerLevel,
            'best_of' => $this->bestOf,
            'confirmation_hours' => $this->confirmationHours,
            'min_players_for_groups' => $this->minPlayersForGroups,
            'players_per_group' => $this->playersPerGroup,
            'advance_per_group' => $this->advancePerGroup,
        ];
    }
}
