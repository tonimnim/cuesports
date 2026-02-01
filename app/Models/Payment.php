<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payable_type',
        'payable_id',
        'reference',
        'amount',
        'currency',
        'status',
        'payment_method', // 'mpesa', 'mtn', 'card', etc.
        'phone_number',
        // M-Pesa specific
        'mpesa_checkout_request_id',
        'mpesa_merchant_request_id',
        'mpesa_receipt_number',
        // Legacy Paystack fields (kept for migration compatibility)
        'paystack_reference',
        'access_code',
        'authorization_url',
        'paystack_fees',
        'channel',
        'card_type',
        'card_last4',
        'bank',
        // Common fields
        'metadata',
        'provider_response',
        'failure_reason',
        'paid_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paystack_fees' => 'integer',
        'metadata' => 'array',
        'provider_response' => 'array',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->reference)) {
                $payment->reference = self::generateReference();
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForTournament($query)
    {
        return $query->where('payable_type', Tournament::class);
    }

    public function scopeForSubscription($query)
    {
        return $query->where('payable_type', Subscription::class);
    }

    // Status Checks

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isAbandoned(): bool
    {
        return $this->status === 'abandoned';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    // Accessors

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->amount / 100, 2);
    }

    public function getPaymentMethodAttribute(): string
    {
        if ($this->channel === 'card' && $this->card_type) {
            return ucfirst($this->card_type) . ' ****' . $this->card_last4;
        }

        if ($this->channel === 'bank' && $this->bank) {
            return $this->bank;
        }

        return ucfirst($this->channel ?? 'Unknown');
    }

    public function getIsTournamentEntryAttribute(): bool
    {
        return $this->payable_type === Tournament::class;
    }

    public function getIsSubscriptionAttribute(): bool
    {
        return $this->payable_type === Subscription::class;
    }

    // Status Updates

    public function markAsSuccessful(array $providerData = []): void
    {
        $this->status = 'success';
        $this->paid_at = now();
        $this->provider_response = $providerData;

        // M-Pesa specific fields
        if (isset($providerData['MpesaReceiptNumber'])) {
            $this->mpesa_receipt_number = $providerData['MpesaReceiptNumber'];
        }
        if (isset($providerData['mpesa_receipt_number'])) {
            $this->mpesa_receipt_number = $providerData['mpesa_receipt_number'];
        }

        // Legacy Paystack fields
        if (isset($providerData['reference'])) {
            $this->paystack_reference = $providerData['reference'];
        }
        if (isset($providerData['fees'])) {
            $this->paystack_fees = $providerData['fees'];
        }
        if (isset($providerData['channel'])) {
            $this->channel = $providerData['channel'];
        }
        if (isset($providerData['authorization'])) {
            $auth = $providerData['authorization'];
            $this->card_type = $auth['card_type'] ?? null;
            $this->card_last4 = $auth['last4'] ?? null;
            $this->bank = $auth['bank'] ?? null;
        }

        $this->save();
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->status = 'failed';
        $this->failure_reason = $reason;
        $this->save();
    }

    public function markAsAbandoned(): void
    {
        $this->status = 'abandoned';
        $this->save();
    }

    public function markAsRefunded(): void
    {
        $this->status = 'refunded';
        $this->refunded_at = now();
        $this->save();
    }

    // Helpers

    public static function generateReference(): string
    {
        return 'CUE_' . strtoupper(Str::random(16));
    }

    public function getPayableDescription(): string
    {
        if ($this->is_tournament_entry) {
            return 'Tournament Entry: ' . ($this->payable?->name ?? 'Unknown');
        }

        if ($this->is_subscription) {
            return 'Subscription: ' . ($this->payable?->plan?->name ?? 'Unknown');
        }

        return 'Payment';
    }
}
