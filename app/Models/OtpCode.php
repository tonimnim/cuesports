<?php

namespace App\Models;

use App\Enums\OtpType;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'identifier',
        'code',
        'type',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'type' => OtpType::class,
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isVerified();
    }

    public function markAsVerified(): void
    {
        $this->verified_at = now();
        $this->save();
    }

    public function scopeForIdentifier($query, string $identifier)
    {
        return $query->where('identifier', $identifier);
    }

    public function scopeOfType($query, OtpType $type)
    {
        return $query->where('type', $type->value);
    }

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereNull('verified_at');
    }
}
