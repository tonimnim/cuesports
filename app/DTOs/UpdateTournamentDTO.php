<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class UpdateTournamentDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $venueName = null,
        public ?string $venueAddress = null,
        public ?Carbon $registrationOpensAt = null,
        public ?Carbon $registrationClosesAt = null,
        public ?Carbon $startsAt = null,
        public ?int $winnersCount = null,
        public ?int $raceTo = null,
        public ?int $finalsRaceTo = null,
        public ?int $confirmationHours = null,
        public ?int $entryFee = null,
        public ?string $entryFeeCurrency = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            venueName: $data['venue_name'] ?? null,
            venueAddress: $data['venue_address'] ?? null,
            registrationOpensAt: isset($data['registration_opens_at']) ? Carbon::parse($data['registration_opens_at']) : null,
            registrationClosesAt: isset($data['registration_closes_at']) ? Carbon::parse($data['registration_closes_at']) : null,
            startsAt: isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null,
            winnersCount: $data['winners_count'] ?? null,
            raceTo: $data['race_to'] ?? null,
            finalsRaceTo: $data['finals_race_to'] ?? null,
            confirmationHours: $data['confirmation_hours'] ?? null,
            entryFee: $data['entry_fee'] ?? null,
            entryFeeCurrency: $data['entry_fee_currency'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'venue_name' => $this->venueName,
            'venue_address' => $this->venueAddress,
            'registration_opens_at' => $this->registrationOpensAt,
            'registration_closes_at' => $this->registrationClosesAt,
            'starts_at' => $this->startsAt,
            'winners_count' => $this->winnersCount,
            'race_to' => $this->raceTo,
            'finals_race_to' => $this->finalsRaceTo,
            'confirmation_hours' => $this->confirmationHours,
            'entry_fee' => $this->entryFee,
            'entry_fee_currency' => $this->entryFeeCurrency,
        ], fn($value) => $value !== null);

        // Add requires_payment if entry_fee is being updated
        if (isset($data['entry_fee'])) {
            $data['requires_payment'] = $data['entry_fee'] > 0;
        }

        return $data;
    }

    public function hasChanges(): bool
    {
        return !empty($this->toArray());
    }
}
