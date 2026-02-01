<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case SUPPORT_CONFIRMED = 'support_confirmed';
    case ADMIN_APPROVED = 'admin_approved';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'Pending Review',
            self::SUPPORT_CONFIRMED => 'Awaiting Admin Approval',
            self::ADMIN_APPROVED => 'Approved',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get badge color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'yellow',
            self::SUPPORT_CONFIRMED => 'blue',
            self::ADMIN_APPROVED => 'indigo',
            self::PROCESSING => 'purple',
            self::COMPLETED => 'green',
            self::REJECTED => 'red',
            self::CANCELLED => 'gray',
        };
    }

    /**
     * Check if support can review this payout.
     */
    public function canBeReviewedBySupport(): bool
    {
        return $this === self::PENDING_REVIEW;
    }

    /**
     * Check if admin can approve this payout.
     */
    public function canBeApprovedByAdmin(): bool
    {
        return $this === self::SUPPORT_CONFIRMED;
    }

    /**
     * Check if the payout can be processed for payment.
     */
    public function canBeProcessed(): bool
    {
        return $this === self::ADMIN_APPROVED;
    }

    /**
     * Check if this is a final state (no further transitions possible).
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::REJECTED,
            self::CANCELLED,
        ]);
    }

    /**
     * Check if payout is in a pending state (not yet finalized).
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::PENDING_REVIEW,
            self::SUPPORT_CONFIRMED,
            self::ADMIN_APPROVED,
            self::PROCESSING,
        ]);
    }

    /**
     * Check if organizer can cancel this payout.
     */
    public function canBeCancelledByOrganizer(): bool
    {
        return in_array($this, [
            self::PENDING_REVIEW,
            self::SUPPORT_CONFIRMED,
        ]);
    }

    /**
     * Get all statuses that are awaiting action.
     */
    public static function awaitingAction(): array
    {
        return [
            self::PENDING_REVIEW,
            self::SUPPORT_CONFIRMED,
            self::ADMIN_APPROVED,
        ];
    }

    /**
     * Get valid transitions from current status.
     */
    public function validTransitions(): array
    {
        return match ($this) {
            self::PENDING_REVIEW => [self::SUPPORT_CONFIRMED, self::REJECTED, self::CANCELLED],
            self::SUPPORT_CONFIRMED => [self::ADMIN_APPROVED, self::REJECTED, self::CANCELLED],
            self::ADMIN_APPROVED => [self::PROCESSING, self::REJECTED],
            self::PROCESSING => [self::COMPLETED, self::ADMIN_APPROVED], // Can go back to approved if payment fails
            self::COMPLETED, self::REJECTED, self::CANCELLED => [],
        };
    }

    /**
     * Check if transition to given status is valid.
     */
    public function canTransitionTo(PayoutStatus $status): bool
    {
        return in_array($status, $this->validTransitions());
    }
}
