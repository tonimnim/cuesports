<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'phone_number',
        'email',
        'password',
        'country_id',
        'is_super_admin',
        'is_support',
        'is_player',
        'is_organizer',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_support' => 'boolean',
            'is_player' => 'boolean',
            'is_organizer' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function country(): BelongsTo
    {
        return $this->belongsTo(GeographicUnit::class, 'country_id');
    }

    public function playerProfile(): HasOne
    {
        return $this->hasOne(PlayerProfile::class);
    }

    public function organizerProfile(): HasOne
    {
        return $this->hasOne(OrganizerProfile::class);
    }

    // Role Checks

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function isSupport(): bool
    {
        return $this->is_support;
    }

    public function isPlayer(): bool
    {
        return $this->is_player;
    }

    public function isOrganizer(): bool
    {
        return $this->is_organizer;
    }

    public function isAdmin(): bool
    {
        return $this->is_super_admin || $this->is_support;
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            $method = 'is' . ucfirst($role);
            if (method_exists($this, $method) && $this->$method()) {
                return true;
            }
        }
        return false;
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }

    public function scopeSupport($query)
    {
        return $query->where('is_support', true);
    }

    public function scopePlayers($query)
    {
        return $query->where('is_player', true);
    }

    public function scopeOrganizers($query)
    {
        return $query->where('is_organizer', true);
    }

    public function scopeInCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    // Helpers

    public function getCountryCode(): ?string
    {
        return $this->country?->country_code;
    }

    public function getCountryName(): ?string
    {
        return $this->country?->name;
    }

    public function isVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function markPhoneAsVerified(): void
    {
        $this->phone_verified_at = now();
        $this->save();
    }

    public function canCreateRegularTournament(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->is_organizer) {
            $profile = $this->organizerProfile;
            return $profile && $profile->canCreateTournaments();
        }

        return false;
    }

    public function canCreateSpecialTournament(): bool
    {
        return $this->is_active && $this->is_super_admin;
    }
}
