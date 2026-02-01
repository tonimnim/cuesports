<?php

namespace App\Enums;

enum ForfeitType: string
{
    case NO_SHOW = 'no_show';
    case DOUBLE_FORFEIT = 'double_forfeit';
    case WALKOVER = 'walkover';
    case TIME_EXPIRED = 'time_expired';

    public function label(): string
    {
        return match($this) {
            self::NO_SHOW => 'No Show',
            self::DOUBLE_FORFEIT => 'Double Forfeit',
            self::WALKOVER => 'Walkover',
            self::TIME_EXPIRED => 'Time Expired',
        };
    }
}
