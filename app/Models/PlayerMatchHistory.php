<?php

namespace App\Models;

use App\Enums\GeographicLevel;
use App\Enums\MatchType;
use App\Enums\TournamentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMatchHistory extends Model
{
    use HasFactory;

    protected $table = 'player_match_history';

    protected $fillable = [
        'player_profile_id',
        'match_id',
        'tournament_id',
        'opponent_profile_id',
        'opponent_name',
        'opponent_rating_at_time',
        'won',
        'is_bye',
        'frames_won',
        'frames_lost',
        'score',
        'rating_before',
        'rating_after',
        'rating_change',
        'tournament_name',
        'tournament_type',
        'match_type',
        'round_number',
        'round_name',
        'geographic_unit_id',
        'geographic_level',
        'played_at',
    ];

    protected $casts = [
        'won' => 'boolean',
        'is_bye' => 'boolean',
        'frames_won' => 'integer',
        'frames_lost' => 'integer',
        'opponent_rating_at_time' => 'integer',
        'rating_before' => 'integer',
        'rating_after' => 'integer',
        'rating_change' => 'integer',
        'round_number' => 'integer',
        'played_at' => 'datetime',
    ];

    // Relationships

    public function playerProfile(): BelongsTo
    {
        return $this->belongsTo(PlayerProfile::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Match::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function opponentProfile(): BelongsTo
    {
        return $this->belongsTo(PlayerProfile::class, 'opponent_profile_id');
    }

    public function geographicUnit(): BelongsTo
    {
        return $this->belongsTo(GeographicUnit::class);
    }

    // Scopes

    public function scopeForPlayer($query, int $playerProfileId)
    {
        return $query->where('player_profile_id', $playerProfileId);
    }

    public function scopeWins($query)
    {
        return $query->where('won', true);
    }

    public function scopeLosses($query)
    {
        return $query->where('won', false);
    }

    public function scopeExcludingByes($query)
    {
        return $query->where('is_bye', false);
    }

    public function scopeByTournamentType($query, string $type)
    {
        return $query->where('tournament_type', $type);
    }

    public function scopeByMatchType($query, string $type)
    {
        return $query->where('match_type', $type);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('played_at', [$startDate, $endDate]);
    }

    public function scopeAgainstOpponent($query, int $opponentProfileId)
    {
        return $query->where('opponent_profile_id', $opponentProfileId);
    }

    public function scopeInTournament($query, int $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }

    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('played_at', 'desc')->limit($limit);
    }

    // Accessors

    public function getFrameDifferenceAttribute(): int
    {
        return $this->frames_won - $this->frames_lost;
    }

    public function getRatingChangeFormattedAttribute(): string
    {
        $prefix = $this->rating_change > 0 ? '+' : '';
        return $prefix . $this->rating_change;
    }

    public function getResultAttribute(): string
    {
        if ($this->is_bye) {
            return 'Bye';
        }

        return $this->won ? 'Win' : 'Loss';
    }

    public function getTournamentTypeEnumAttribute(): TournamentType
    {
        return TournamentType::from($this->tournament_type);
    }

    public function getMatchTypeEnumAttribute(): MatchType
    {
        return MatchType::from($this->match_type);
    }

    public function getGeographicLevelEnumAttribute(): ?GeographicLevel
    {
        return $this->geographic_level ? GeographicLevel::from($this->geographic_level) : null;
    }

    // Static factory for creating from a Match
    // Note: Match uses TournamentParticipant, which links to PlayerProfile

    public static function createFromMatch(
        Match $match,
        TournamentParticipant $participant,
        int $ratingBefore,
        int $ratingAfter
    ): self {
        $playerProfile = $participant->playerProfile;
        $isPlayer1 = $match->player1_id === $participant->id;
        $opponentParticipant = $isPlayer1 ? $match->player2 : $match->player1;
        $opponentProfile = $opponentParticipant?->playerProfile;

        $framesWon = $isPlayer1 ? $match->player1_score : $match->player2_score;
        $framesLost = $isPlayer1 ? $match->player2_score : $match->player1_score;
        $won = $match->winner_id === $participant->id;

        return self::create([
            'player_profile_id' => $playerProfile->id,
            'match_id' => $match->id,
            'tournament_id' => $match->tournament_id,
            'opponent_profile_id' => $opponentProfile?->id,
            'opponent_name' => $opponentProfile?->display_name ?? 'Bye',
            'opponent_rating_at_time' => $opponentProfile?->rating ?? 0,
            'won' => $won,
            'is_bye' => $match->match_type === MatchType::BYE,
            'frames_won' => $framesWon,
            'frames_lost' => $framesLost,
            'score' => "{$framesWon}-{$framesLost}",
            'rating_before' => $ratingBefore,
            'rating_after' => $ratingAfter,
            'rating_change' => $ratingAfter - $ratingBefore,
            'tournament_name' => $match->tournament->name,
            'tournament_type' => $match->tournament->type->value,
            'match_type' => $match->match_type->value,
            'round_number' => $match->round_number,
            'round_name' => $match->round_name,
            'geographic_unit_id' => $match->geographic_unit_id,
            'geographic_level' => $match->geographicUnit?->level?->value,
            'played_at' => $match->played_at ?? $match->confirmed_at ?? now(),
        ]);
    }
}
