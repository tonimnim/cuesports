<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRequest extends Model
{
    protected $fillable = [
        'organizer_profile_id',
        'payout_method_id',
        'amount',
        'currency',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'payment_reference',
        'paid_at',
        'payment_response',
        // M-Pesa fields
        'mpesa_conversation_id',
        'mpesa_originator_conversation_id',
        'mpesa_transaction_id',
        'failure_reason',
    ];

    protected $casts = [
        'status' => PayoutStatus::class,
        'amount' => 'integer',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_response' => 'array',
    ];

    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(PayoutMethod::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount / 100, 2);
    }

    // Status transition methods
    public function confirmBySupport(User $support, ?string $notes = null): void
    {
        $this->status = PayoutStatus::SUPPORT_CONFIRMED;
        $this->reviewed_by = $support->id;
        $this->reviewed_at = now();
        $this->review_notes = $notes;
        $this->save();
    }

    public function approveByAdmin(User $admin, ?string $notes = null): void
    {
        $this->status = PayoutStatus::ADMIN_APPROVED;
        $this->approved_by = $admin->id;
        $this->approved_at = now();
        $this->approval_notes = $notes;
        $this->save();
    }

    public function reject(User $rejector, string $reason): void
    {
        $this->status = PayoutStatus::REJECTED;
        $this->rejected_by = $rejector->id;
        $this->rejected_at = now();
        $this->rejection_reason = $reason;
        $this->save();
    }

    public function markAsProcessing(): void
    {
        $this->status = PayoutStatus::PROCESSING;
        $this->save();
    }

    public function markAsCompleted(string $reference, ?array $response = null): void
    {
        $this->status = PayoutStatus::COMPLETED;
        $this->payment_reference = $reference;
        $this->paid_at = now();
        $this->payment_response = $response;
        $this->save();
    }

    // Scopes
    public function scopePendingReview($query)
    {
        return $query->where('status', PayoutStatus::PENDING_REVIEW);
    }

    public function scopeSupportConfirmed($query)
    {
        return $query->where('status', PayoutStatus::SUPPORT_CONFIRMED);
    }

    public function scopeAdminApproved($query)
    {
        return $query->where('status', PayoutStatus::ADMIN_APPROVED);
    }
}
