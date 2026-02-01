<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case DRAFT = 'draft';
    case REGISTRATION = 'registration';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'Pending Review',
            self::DRAFT => 'Draft',
            self::REGISTRATION => 'Registration Open',
            self::ACTIVE => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isPendingReview(): bool
    {
        return $this === self::PENDING_REVIEW;
    }

    public function canRegister(): bool
    {
        return $this === self::REGISTRATION;
    }

    public function canPlay(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED]);
    }
}
