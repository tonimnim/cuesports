<?php

namespace App\Enums;

enum ParticipantStatus: string
{
    case REGISTERED = 'registered';
    case ACTIVE = 'active';
    case ELIMINATED = 'eliminated';
    case DISQUALIFIED = 'disqualified';
    case WINNER = 'winner';

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'Registered',
            self::ACTIVE => 'Active',
            self::ELIMINATED => 'Eliminated',
            self::DISQUALIFIED => 'Disqualified',
            self::WINNER => 'Winner',
        };
    }

    public function canPlay(): bool
    {
        return in_array($this, [self::REGISTERED, self::ACTIVE]);
    }

    public function isOut(): bool
    {
        return in_array($this, [self::ELIMINATED, self::DISQUALIFIED]);
    }
}
