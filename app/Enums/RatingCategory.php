<?php

namespace App\Enums;

enum RatingCategory: string
{
    case BEGINNER = 'beginner';         // < 1200
    case INTERMEDIATE = 'intermediate'; // 1200 - 1599
    case ADVANCED = 'advanced';         // 1600 - 1999
    case PRO = 'pro';                   // 2000+

    public function label(): string
    {
        return match ($this) {
            self::BEGINNER => 'Beginner',
            self::INTERMEDIATE => 'Intermediate',
            self::ADVANCED => 'Advanced',
            self::PRO => 'Pro',
        };
    }

    public function ratingRange(): string
    {
        return match ($this) {
            self::BEGINNER => 'Under 1200',
            self::INTERMEDIATE => '1200 - 1599',
            self::ADVANCED => '1600 - 1999',
            self::PRO => '2000+',
        };
    }

    public static function fromRating(int $rating): self
    {
        return match (true) {
            $rating < 1200 => self::BEGINNER,
            $rating < 1600 => self::INTERMEDIATE,
            $rating < 2000 => self::ADVANCED,
            default => self::PRO,
        };
    }
}
