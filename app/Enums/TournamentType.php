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

    /**
     * Get the rating multiplier for this tournament type at a given geographic level.
     *
     * Regular tournaments always have 1.0x multiplier.
     * Special tournaments have tiered multipliers based on geographic level:
     * - ATOMIC (Community): 1.0x (same as regular - grassroots level)
     * - NANO (Ward): 1.0x
     * - MICRO (Constituency): 1.1x
     * - MESO (County/District): 1.15x
     * - MACRO (Region): 1.25x
     * - NATIONAL (Country): 1.5x
     * - ROOT (Continental): 1.75x
     */
    public function getRatingMultiplier(GeographicLevel $level): float
    {
        if ($this === self::REGULAR) {
            return 1.0;
        }

        // Special tournament multipliers based on geographic level
        return match ($level) {
            GeographicLevel::ATOMIC => 1.0,    // Community level - same as regular
            GeographicLevel::NANO => 1.0,      // Ward level - same as regular
            GeographicLevel::MICRO => 1.1,     // Constituency level
            GeographicLevel::MESO => 1.15,     // County/District level
            GeographicLevel::MACRO => 1.25,    // Regional level
            GeographicLevel::NATIONAL => 1.5,  // National level
            GeographicLevel::ROOT => 1.75,     // Continental level
        };
    }
}
