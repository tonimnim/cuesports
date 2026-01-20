<?php

namespace App\Enums;

enum TournamentType: string
{
    case REGULAR = 'regular';
    case SPECIAL = 'special';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::SPECIAL => 'Special',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::REGULAR => 'Open tournament - any registered player can participate',
            self::SPECIAL => 'Progressive tournament - players advance through geographic levels',
        };
    }
}
