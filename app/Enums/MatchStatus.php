<?php

namespace App\Enums;

enum MatchStatus: string
{
    case SCHEDULED = 'scheduled';
    case PENDING_CONFIRMATION = 'pending_confirmation';
    case COMPLETED = 'completed';
    case DISPUTED = 'disputed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::PENDING_CONFIRMATION => 'Pending Confirmation',
            self::COMPLETED => 'Completed',
            self::DISPUTED => 'Disputed',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function canSubmitResult(): bool
    {
        return $this === self::SCHEDULED;
    }

    public function canConfirm(): bool
    {
        return $this === self::PENDING_CONFIRMATION;
    }

    public function canDispute(): bool
    {
        return $this === self::PENDING_CONFIRMATION;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::EXPIRED, self::CANCELLED]);
    }

    public function requiresAction(): bool
    {
        return in_array($this, [self::SCHEDULED, self::PENDING_CONFIRMATION, self::DISPUTED]);
    }
}
