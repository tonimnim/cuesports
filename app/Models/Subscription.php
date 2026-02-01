<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_code',
        'paystack_subscription_code',
        'paystack_email_token',
        'paystack_customer_code',
        'authorization_code',
        'status',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'cancellation_reason',
        'tournaments_used',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'tournaments_used' => 'integer',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    // Plan from Config

    public function getPlanAttribute(): ?array
    {
        return config("paystack.plans.{$this->plan_code}");
    }

    public function getPlanNameAttribute(): string
    {
        return $this->plan['name'] ?? ucfirst($this->plan_code);
    }

    public function getPlanAmountAttribute(): int
    {
        return $this->plan['amount'] ?? 0;
    }

    public function getTournamentsLimitAttribute(): ?int
    {
        return $this->plan['tournaments_limit'] ?? null;
    }

    public function getPlayersLimitAttribute(): ?int
    {
        return $this->plan['players_limit'] ?? null;
    }

    public function getIsUnlimitedTournamentsAttribute(): bool
    {
        return is_null($this->tournaments_limit);
    }

    public function getIsUnlimitedPlayersAttribute(): bool
    {
        return is_null($this->players_limit);
    }

    public function getCanCollectEntryFeeAttribute(): bool
    {
        return $this->plan['can_collect_entry_fee'] ?? false;
    }

    public function getEntryFeePercentageAttribute(): float
    {
        return $this->plan['entry_fee_percentage'] ?? 0;
    }

    public function getEntryFeeFlatAttribute(): int
    {
        return $this->plan['entry_fee_flat'] ?? 0;
    }

    public function getShowBrandingAttribute(): bool
    {
        return $this->plan['show_branding'] ?? true;
    }

    public function getOrganizerAccountsAttribute(): int
    {
        return $this->plan['organizer_accounts'] ?? 1;
    }

    public function getIsFreeAttribute(): bool
    {
        return ($this->plan['amount'] ?? 0) === 0;
    }

    public function getIsPopularAttribute(): bool
    {
        return $this->plan['is_popular'] ?? false;
    }

    public function getFeaturesAttribute(): array
    {
        return $this->plan['features'] ?? [];
    }

    public function getLimitationsAttribute(): array
    {
        return $this->plan['limitations'] ?? [];
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('current_period_end', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('current_period_end', '<=', now());
    }

    public function scopeForPlan($query, string $planCode)
    {
        return $query->where('plan_code', $planCode);
    }

    // Status Checks

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->current_period_end->isFuture();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        return $this->current_period_end && $this->current_period_end->isPast();
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    // Usage Tracking

    public function canHostMoreTournaments(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->is_unlimited_tournaments) {
            return true;
        }

        return $this->tournaments_used < $this->tournaments_limit;
    }

    public function canHaveMorePlayers(int $currentPlayers): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->is_unlimited_players) {
            return true;
        }

        return $currentPlayers < $this->players_limit;
    }

    public function canCollectEntryFees(): bool
    {
        return $this->isActive() && $this->can_collect_entry_fee;
    }

    public function incrementTournamentsUsed(): void
    {
        $this->increment('tournaments_used');
    }

    public function getRemainingTournamentsAttribute(): ?int
    {
        if ($this->is_unlimited_tournaments) {
            return null;
        }

        return max(0, $this->tournaments_limit - $this->tournaments_used);
    }

    // Fee Calculations

    public function calculatePlatformFee(int $entryFeeAmount): int
    {
        if (!$this->can_collect_entry_fee) {
            return 0;
        }

        $percentageFee = (int) round($entryFeeAmount * ($this->entry_fee_percentage / 100));
        $flatFee = $this->entry_fee_flat;

        return $percentageFee + $flatFee;
    }

    public function calculateOrganizerAmount(int $entryFeeAmount): int
    {
        return $entryFeeAmount - $this->calculatePlatformFee($entryFeeAmount);
    }

    // Lifecycle

    public function activate(string $authorizationCode = null): void
    {
        $this->status = 'active';
        $this->current_period_start = now();
        $this->current_period_end = now()->addMonth();
        $this->tournaments_used = 0;

        if ($authorizationCode) {
            $this->authorization_code = $authorizationCode;
        }

        $this->save();
    }

    public function renew(): void
    {
        $this->current_period_start = now();
        $this->current_period_end = now()->addMonth();
        $this->tournaments_used = 0;
        $this->status = 'active';
        $this->save();
    }

    public function cancel(string $reason = null): void
    {
        $this->status = 'cancelled';
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;
        $this->save();
    }

    public function expire(): void
    {
        $this->status = 'expired';
        $this->save();
    }

    public function markPastDue(): void
    {
        $this->status = 'past_due';
        $this->save();
    }

    // Helpers

    public function getDaysRemainingAttribute(): int
    {
        if (!$this->current_period_end) {
            return 0;
        }

        return max(0, now()->diffInDays($this->current_period_end, false));
    }

    public function getFormattedAmountAttribute(): string
    {
        if ($this->is_free) {
            return 'Free';
        }

        return '$' . number_format($this->plan_amount / 100, 2) . '/month';
    }
}
