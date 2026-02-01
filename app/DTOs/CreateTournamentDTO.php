<?php

namespace App\DTOs;

use App\Enums\TournamentFormat;
use App\Enums\TournamentType;
use Carbon\Carbon;

readonly class CreateTournamentDTO
{
    public function __construct(
        public string $name,
        public int $geographicScopeId,
        public Carbon $registrationClosesAt,
        public Carbon $startsAt,
        public TournamentType $type = TournamentType::REGULAR,
        public TournamentFormat $format = TournamentFormat::KNOCKOUT,
        public ?string $description = null,
        public ?string $venueName = null,
        public ?string $venueAddress = null,
        public ?Carbon $registrationOpensAt = null,
        public int $winnersCount = 3,
        public int $winnersPerLevel = 2,
        public int $raceTo = 3,
        public ?int $finalsRaceTo = null,
        public int $confirmationHours = 24,
        public int $entryFee = 0,
        public string $entryFeeCurrency = 'KES',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            geographicScopeId: $data['geographic_scope_id'],
            registrationClosesAt: Carbon::parse($data['registration_closes_at']),
            startsAt: Carbon::parse($data['starts_at']),
            type: isset($data['type']) ? TournamentType::from($data['type']) : TournamentType::REGULAR,
            format: TournamentFormat::KNOCKOUT,
            description: $data['description'] ?? null,
            venueName: $data['venue_name'] ?? null,
            venueAddress: $data['venue_address'] ?? null,
            registrationOpensAt: isset($data['registration_opens_at']) ? Carbon::parse($data['registration_opens_at']) : null,
            winnersCount: $data['winners_count'] ?? 3,
            winnersPerLevel: $data['winners_per_level'] ?? 2,
            raceTo: $data['race_to'] ?? 3,
            finalsRaceTo: $data['finals_race_to'] ?? null,
            confirmationHours: $data['confirmation_hours'] ?? 24,
            entryFee: $data['entry_fee'] ?? 0,
            entryFeeCurrency: $data['entry_fee_currency'] ?? 'KES',
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
            'venue_name' => $this->venueName,
            'venue_address' => $this->venueAddress,
            'registration_opens_at' => $this->registrationOpensAt,
            'registration_closes_at' => $this->registrationClosesAt,
            'starts_at' => $this->startsAt,
            'winners_count' => $this->winnersCount,
            'winners_per_level' => $this->winnersPerLevel,
            'race_to' => $this->raceTo,
            'finals_race_to' => $this->finalsRaceTo,
            'confirmation_hours' => $this->confirmationHours,
            'entry_fee' => $this->entryFee,
            'entry_fee_currency' => $this->entryFeeCurrency,
            'requires_payment' => $this->entryFee > 0,
        ];
    }
}
