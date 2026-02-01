<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrganizerPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_profile_id',
        'reference',
        'paystack_transfer_code',
        'paystack_reference',
        'amount',
        'currency',
        'platform_fee',
        'net_amount',
        'status',
        'recipient_code',
        'bank_name',
        'account_number',
        'account_name',
        'failure_reason',
        'tournaments',
        'requested_at',
        'processed_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'platform_fee' => 'integer',
        'net_amount' => 'integer',
        'tournaments' => 'array',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payout) {
            if (empty($payout->reference)) {
                $payout->reference = self::generateReference();
            }
            if (empty($payout->requested_at)) {
                $payout->requested_at = now();
            }
        });
    }

    // Relationships

    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // Status Checks

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    // Status Updates

    public function markAsProcessing(string $transferCode = null): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->paystack_transfer_code = $transferCode;
        $this->processed_at = now();
        $this->save();
    }

    public function markAsSuccessful(string $paystackReference = null): void
    {
        $this->status = self::STATUS_SUCCESS;
        $this->paystack_reference = $paystackReference;
        $this->completed_at = now();
        $this->save();

        // Update organizer balance
        $this->organizerProfile->increment('total_withdrawn', $this->net_amount);
        $this->organizerProfile->decrement('available_balance', $this->net_amount);
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failure_reason = $reason;
        $this->save();

        // Return funds to available balance
        if ($this->isProcessing()) {
            $this->organizerProfile->increment('available_balance', $this->net_amount);
        }
    }

    // Accessors

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount / 100, 2);
    }

    public function getFormattedNetAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->net_amount / 100, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_SUCCESS => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_REVERSED => 'Reversed',
            default => ucfirst($this->status),
        };
    }

    // Helpers

    public static function generateReference(): string
    {
        return 'PAYOUT_' . strtoupper(Str::random(16));
    }

    public function getTournamentCount(): int
    {
        return is_array($this->tournaments) ? count($this->tournaments) : 0;
    }
}
