<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerRatingHistory extends Model
{
    use HasFactory;

    protected $table = 'player_rating_history';

    protected $fillable = [
        'player_profile_id',
        'old_rating',
        'new_rating',
        'change',
        'reason',
        'match_id',
        'tournament_id',
    ];

    protected $casts = [
        'old_rating' => 'integer',
        'new_rating' => 'integer',
        'change' => 'integer',
    ];

    // Reason constants
    public const REASON_MATCH_RESULT = 'match_result';
    public const REASON_ADMIN_ADJUSTMENT = 'admin_adjustment';
    public const REASON_TOURNAMENT_BONUS = 'tournament_bonus';
    public const REASON_DECAY = 'decay';

    // Relationships

    public function playerProfile(): BelongsTo
    {
        return $this->belongsTo(PlayerProfile::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    // Scopes

    public function scopeForPlayer($query, int $playerProfileId)
    {
        return $query->where('player_profile_id', $playerProfileId);
    }

    public function scopeByReason($query, string $reason)
    {
        return $query->where('reason', $reason);
    }

    public function scopeGains($query)
    {
        return $query->where('change', '>', 0);
    }

    public function scopeLosses($query)
    {
        return $query->where('change', '<', 0);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Helpers

    public function isGain(): bool
    {
        return $this->change > 0;
    }

    public function isLoss(): bool
    {
        return $this->change < 0;
    }

    public function getAbsoluteChange(): int
    {
        return abs($this->change);
    }

    public function getFormattedChange(): string
    {
        $prefix = $this->change > 0 ? '+' : '';
        return $prefix . $this->change;
    }

    public function getReasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_MATCH_RESULT => 'Match Result',
            self::REASON_ADMIN_ADJUSTMENT => 'Admin Adjustment',
            self::REASON_TOURNAMENT_BONUS => 'Tournament Bonus',
            self::REASON_DECAY => 'Inactivity Decay',
            default => ucfirst(str_replace('_', ' ', $this->reason)),
        };
    }
}
