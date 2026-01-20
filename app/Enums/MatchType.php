<?php

namespace App\Enums;

enum MatchType: string
{
    case REGULAR = 'regular';
    case QUARTER_FINAL = 'quarter_final';
    case SEMI_FINAL = 'semi_final';
    case FINAL = 'final';
    case THIRD_PLACE = 'third_place';
    case BYE = 'bye';
    case GROUP = 'group';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::QUARTER_FINAL => 'Quarter-Final',
            self::SEMI_FINAL => 'Semi-Final',
            self::FINAL => 'Final',
            self::THIRD_PLACE => 'Third Place',
            self::BYE => 'Bye',
            self::GROUP => 'Group Stage',
        };
    }

    public function isBye(): bool
    {
        return $this === self::BYE;
    }

    public function isPlayoff(): bool
    {
        return in_array($this, [
            self::QUARTER_FINAL,
            self::SEMI_FINAL,
            self::FINAL,
            self::THIRD_PLACE,
        ]);
    }

    public function isConsolation(): bool
    {
        return $this === self::THIRD_PLACE;
    }

    public function determinesPosition(): bool
    {
        return in_array($this, [self::FINAL, self::THIRD_PLACE]);
    }
}
