<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrganizerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_name',
        'description',
        'logo_url',
        'api_key',
        'api_secret',
        'is_active',
        'tournaments_hosted',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tournaments_hosted' => 'integer',
        'api_key_last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'api_secret',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'organizer_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // API Key Management

    public static function generateApiKey(): string
    {
        return 'csk_' . Str::random(60);
    }

    public static function generateApiSecret(): string
    {
        return 'css_' . Str::random(60);
    }

    public function regenerateApiCredentials(): array
    {
        $apiKey = self::generateApiKey();
        $apiSecret = self::generateApiSecret();

        $this->api_key = $apiKey;
        $this->api_secret = bcrypt($apiSecret);
        $this->save();

        // Return plain text secret (only shown once)
        return [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ];
    }

    public function validateApiSecret(string $secret): bool
    {
        return password_verify($secret, $this->api_secret);
    }

    public function recordApiUsage(): void
    {
        $this->api_key_last_used_at = now();
        $this->saveQuietly();
    }

    public function revokeApiCredentials(): void
    {
        $this->api_key = null;
        $this->api_secret = null;
        $this->save();
    }

    // Helpers

    public function canCreateTournaments(): bool
    {
        return $this->is_active;
    }

    public function incrementTournamentsHosted(): void
    {
        $this->increment('tournaments_hosted');
    }

    public function getContactEmail(): string
    {
        return $this->user->email;
    }

    public function getContactPhone(): string
    {
        return $this->user->phone_number;
    }
}
