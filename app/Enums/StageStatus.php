<?php

namespace App\Enums;

enum StageStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'In Progress',
            self::COMPLETED => 'Completed',
        };
    }
}
