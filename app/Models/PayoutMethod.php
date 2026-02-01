<?php

namespace App\Models;

use App\Enums\PayoutMethodType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutMethod extends Model
{
    protected $fillable = [
        'organizer_profile_id',
        'type',
        'provider',
        'account_name',
        'account_number',
        'bank_code',
        'is_default',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'type' => PayoutMethodType::class,
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        $number = $this->account_number;
        if (strlen($number) <= 4) {
            return $number;
        }
        return str_repeat('*', strlen($number) - 4) . substr($number, -4);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->type->label() . ' - ' . $this->masked_account_number;
    }
}
