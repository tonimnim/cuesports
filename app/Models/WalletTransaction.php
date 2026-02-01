<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'organizer_wallet_id',
        'type',
        'source',
        'amount',
        'balance_after',
        'currency',
        'description',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(OrganizerWallet::class, 'organizer_wallet_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->type === 'credit' ? '+' : '-';
        return $sign . $this->currency . ' ' . number_format($this->amount / 100, 2);
    }
}
