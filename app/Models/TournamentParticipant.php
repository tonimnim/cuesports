<?php

namespace App\Models;

use App\Enums\ParticipantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'player_profile_id',
        'seed',
        'status',
        'current_stage_id',
        'final_position',
        'matches_played',
        'matches_won',
        'matches_lost',
        'frames_won',
        'frames_lost',
        'frame_difference',
        'points',
        'group_number',
        'group_position',
        'registered_at',
        'eliminated_at',
    ];

    protected $casts = [
        'status' => ParticipantStatus::class,
        'seed' => 'integer',
        'final_position' => 'integer',
        'matches_played' => 'integer',
        'matches_won' => 'integer',
        'matches_lost' => 'integer',
        'frames_won' => 'integer',
        'frames_lost' => 'integer',
        'frame_difference' => 'integer',
        'points' => 'integer',
        'group_number' => 'integer',
        'group_position' => 'integer',
        'registered_at' => 'datetime',
        'eliminated_at' => 'datetime',
    ];

    // Relationships

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function playerProfile(): BelongsTo
    {
        return $this->belongsTo(PlayerProfile::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'current_stage_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            ParticipantStatus::REGISTERED,
            ParticipantStatus::ACTIVE,
        ]);
    }

    public function scopeEliminated($query)
    {
        return $query->where('status', ParticipantStatus::ELIMINATED);
    }

    public function scopeWinners($query)
    {
        return $query->where('status', ParticipantStatus::WINNER);
    }

    public function scopeInGroup($query, int $groupNumber)
    {
        return $query->where('group_number', $groupNumber);
    }

    public function scopeRanked($query)
    {
        return $query->orderByDesc('points')
            ->orderByDesc('frame_difference')
            ->orderByDesc('frames_won');
    }

    // Status Checks

    public function isActive(): bool
    {
        return $this->status->canPlay();
    }

    public function isEliminated(): bool
    {
        return $this->status === ParticipantStatus::ELIMINATED;
    }

    public function isDisqualified(): bool
    {
        return $this->status === ParticipantStatus::DISQUALIFIED;
    }

    public function isWinner(): bool
    {
        return $this->status === ParticipantStatus::WINNER;
    }

    // Stats Methods

    public function getWinRate(): float
    {
        if ($this->matches_played === 0) {
            return 0;
        }

        return round(($this->matches_won / $this->matches_played) * 100, 2);
    }

    public function recordMatchResult(int $framesWon, int $framesLost, bool $won): void
    {
        $this->matches_played++;
        $this->frames_won += $framesWon;
        $this->frames_lost += $framesLost;
        $this->frame_difference = $this->frames_won - $this->frames_lost;
        $this->points = $this->frames_won;

        if ($won) {
            $this->matches_won++;
        } else {
            $this->matches_lost++;
        }

        $this->save();
    }

    // Status Transitions

    public function activate(): void
    {
        $this->status = ParticipantStatus::ACTIVE;
        $this->save();
    }

    public function eliminate(int $position = null): void
    {
        $this->status = ParticipantStatus::ELIMINATED;
        $this->eliminated_at = now();

        if ($position !== null) {
            $this->final_position = $position;
        }

        $this->save();
    }

    public function disqualify(): void
    {
        $this->status = ParticipantStatus::DISQUALIFIED;
        $this->eliminated_at = now();
        $this->save();
    }

    public function markAsWinner(int $position): void
    {
        $this->status = ParticipantStatus::WINNER;
        $this->final_position = $position;
        $this->save();
    }

    public function advanceToStage(TournamentStage $stage): void
    {
        $this->current_stage_id = $stage->id;
        $this->save();
    }

    public function assignToGroup(int $groupNumber): void
    {
        $this->group_number = $groupNumber;
        $this->save();
    }

    public function setGroupPosition(int $position): void
    {
        $this->group_position = $position;
        $this->save();
    }

    public function setSeed(int $seed): void
    {
        $this->seed = $seed;
        $this->save();
    }

    // Helper Methods

    public function getDisplayName(): string
    {
        return $this->playerProfile->display_name;
    }

    public function getRating(): int
    {
        return $this->playerProfile->rating;
    }

    public function getGeographicUnit(): GeographicUnit
    {
        return $this->playerProfile->geographicUnit;
    }
}
