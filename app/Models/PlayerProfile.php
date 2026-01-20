<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\RatingCategory;
use App\Enums\GeographicLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PlayerProfile extends Model
{
    use HasFactory;

    public const DEFAULT_RATING = 1000;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'nickname',
        'national_id_number',
        'photo_url',
        'date_of_birth',
        'gender',
        'geographic_unit_id',
        'rating',
        'rating_category',
        'best_rating',
        'total_matches',
        'wins',
        'losses',
        'lifetime_frames_won',
        'lifetime_frames_lost',
        'lifetime_frame_difference',
        'tournaments_played',
        'tournaments_won',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'gender' => Gender::class,
        'rating_category' => RatingCategory::class,
        'rating' => 'integer',
        'best_rating' => 'integer',
        'total_matches' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
        'lifetime_frames_won' => 'integer',
        'lifetime_frames_lost' => 'integer',
        'lifetime_frame_difference' => 'integer',
        'tournaments_played' => 'integer',
        'tournaments_won' => 'integer',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function geographicUnit(): BelongsTo
    {
        return $this->belongsTo(GeographicUnit::class, 'geographic_unit_id');
    }

    public function ratingHistory(): HasMany
    {
        return $this->hasMany(PlayerRatingHistory::class);
    }

    public function matchHistory(): HasMany
    {
        return $this->hasMany(PlayerMatchHistory::class);
    }

    public function tournamentParticipations(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    // Accessors

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?: $this->full_name;
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    public function getWinRateAttribute(): float
    {
        if ($this->total_matches === 0) {
            return 0.0;
        }

        return round(($this->wins / $this->total_matches) * 100, 2);
    }

    // Scopes

    public function scopeRatingCategory($query, RatingCategory $category)
    {
        return $query->where('rating_category', $category->value);
    }

    public function scopeGender($query, Gender $gender)
    {
        return $query->where('gender', $gender->value);
    }

    public function scopeInGeographicUnit($query, int $unitId)
    {
        return $query->where('geographic_unit_id', $unitId);
    }

    public function scopeMinRating($query, int $rating)
    {
        return $query->where('rating', '>=', $rating);
    }

    public function scopeMaxRating($query, int $rating)
    {
        return $query->where('rating', '<=', $rating);
    }

    // Accessors for Lifetime Stats

    public function getLifetimeWinRateAttribute(): float
    {
        if ($this->total_matches === 0) {
            return 0.0;
        }

        return round(($this->wins / $this->total_matches) * 100, 2);
    }

    public function getLifetimeFrameWinRateAttribute(): float
    {
        $totalFrames = $this->lifetime_frames_won + $this->lifetime_frames_lost;

        if ($totalFrames === 0) {
            return 0.0;
        }

        return round(($this->lifetime_frames_won / $totalFrames) * 100, 2);
    }

    public function getTournamentWinRateAttribute(): float
    {
        if ($this->tournaments_played === 0) {
            return 0.0;
        }

        return round(($this->tournaments_won / $this->tournaments_played) * 100, 2);
    }

    // Rating Methods

    public function updateRating(int $newRating, ?string $reason = null, ?Match $match = null): void
    {
        $oldRating = $this->rating;
        $this->rating = max(0, $newRating);
        $this->rating_category = RatingCategory::fromRating($this->rating);

        // Track best rating
        if ($this->rating > $this->best_rating) {
            $this->best_rating = $this->rating;
        }

        $this->save();

        // Record rating history
        $this->ratingHistory()->create([
            'old_rating' => $oldRating,
            'new_rating' => $this->rating,
            'change' => $this->rating - $oldRating,
            'reason' => $reason ?? 'match_result',
            'match_id' => $match?->id,
        ]);
    }

    public function recordMatch(bool $won, int $framesWon = 0, int $framesLost = 0): void
    {
        $this->total_matches++;
        $this->lifetime_frames_won += $framesWon;
        $this->lifetime_frames_lost += $framesLost;
        $this->lifetime_frame_difference = $this->lifetime_frames_won - $this->lifetime_frames_lost;

        if ($won) {
            $this->wins++;
        } else {
            $this->losses++;
        }

        $this->save();
    }

    public function recordTournamentParticipation(bool $won = false): void
    {
        $this->tournaments_played++;

        if ($won) {
            $this->tournaments_won++;
        }

        $this->save();
    }

    // Geographic Helpers

    public function getLocation(): GeographicUnit
    {
        return $this->geographicUnit;
    }

    public function getLocationAtLevel(GeographicLevel $level): ?GeographicUnit
    {
        return $this->geographicUnit->getAncestorAtLevel($level);
    }

    public function getCountry(): ?GeographicUnit
    {
        return $this->geographicUnit->getCountry();
    }

    public function getLocationPath(): string
    {
        return $this->geographicUnit->getFullPath();
    }

    // Age Category

    public function getAgeCategory(): ?AgeCategory
    {
        return AgeCategory::forAge($this->age);
    }

    public function isInAgeCategory(AgeCategory $category): bool
    {
        return $category->containsAge($this->age);
    }
}
