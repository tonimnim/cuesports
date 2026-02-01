<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizerWallet extends Model
{
    protected $fillable = [
        'organizer_profile_id',
        'balance',
        'pending_balance',
        'total_earned',
        'total_withdrawn',
        'currency',
    ];

    protected $casts = [
        'balance' => 'integer',
        'pending_balance' => 'integer',
        'total_earned' => 'integer',
        'total_withdrawn' => 'integer',
    ];

    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->balance / 100, 2);
    }

    public function getAvailableForWithdrawalAttribute(): int
    {
        return max(0, $this->balance);
    }

    public function credit(int $amount, string $source, ?string $description = null, ?Model $reference = null): WalletTransaction
    {
        $this->balance += $amount;
        $this->total_earned += $amount;
        $this->save();

        return $this->transactions()->create([
            'type' => 'credit',
            'source' => $source,
            'amount' => $amount,
            'balance_after' => $this->balance,
            'currency' => $this->currency,
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
        ]);
    }

    public function debit(int $amount, string $source, ?string $description = null, ?Model $reference = null): WalletTransaction
    {
        $this->balance -= $amount;
        $this->total_withdrawn += $amount;
        $this->save();

        return $this->transactions()->create([
            'type' => 'debit',
            'source' => $source,
            'amount' => $amount,
            'balance_after' => $this->balance,
            'currency' => $this->currency,
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
        ]);
    }
}
