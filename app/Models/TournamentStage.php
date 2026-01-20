<?php

namespace App\Models;

use App\Enums\GeographicLevel;
use App\Enums\StageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'geographic_level',
        'level_name',
        'stage_order',
        'status',
        'started_at',
        'completed_at',
        'participants_count',
        'matches_count',
        'completed_matches_count',
    ];

    protected $casts = [
        'geographic_level' => 'integer',
        'stage_order' => 'integer',
        'status' => StageStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'participants_count' => 'integer',
        'matches_count' => 'integer',
        'completed_matches_count' => 'integer',
    ];

    // Relationships

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class, 'current_stage_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', StageStatus::PENDING);
    }

    public function scopeActive($query)
    {
        return $query->where('status', StageStatus::ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', StageStatus::COMPLETED);
    }

    // Status Checks

    public function isPending(): bool
    {
        return $this->status === StageStatus::PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === StageStatus::ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === StageStatus::COMPLETED;
    }

    // Helper Methods

    public function getLevelEnum(): GeographicLevel
    {
        return GeographicLevel::from($this->geographic_level);
    }

    public function getProgressPercentage(): float
    {
        if ($this->matches_count === 0) {
            return 0;
        }

        return round(($this->completed_matches_count / $this->matches_count) * 100, 1);
    }

    public function allMatchesCompleted(): bool
    {
        return $this->matches_count > 0 && $this->completed_matches_count >= $this->matches_count;
    }

    public function getPreviousStage(): ?TournamentStage
    {
        return $this->tournament->stages()
            ->where('stage_order', $this->stage_order - 1)
            ->first();
    }

    public function getNextStage(): ?TournamentStage
    {
        return $this->tournament->stages()
            ->where('stage_order', $this->stage_order + 1)
            ->first();
    }

    public function isFirstStage(): bool
    {
        return $this->stage_order === 1;
    }

    public function isLastStage(): bool
    {
        $maxOrder = $this->tournament->stages()->max('stage_order');
        return $this->stage_order === $maxOrder;
    }

    // Status Transitions

    public function start(): void
    {
        $this->status = StageStatus::ACTIVE;
        $this->started_at = now();
        $this->save();
    }

    public function complete(): void
    {
        $this->status = StageStatus::COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    // Stats Update

    public function incrementMatchesCount(): void
    {
        $this->increment('matches_count');
    }

    public function incrementCompletedMatchesCount(): void
    {
        $this->increment('completed_matches_count');
    }

    public function updateParticipantsCount(): void
    {
        $this->participants_count = $this->participants()->count();
        $this->save();
    }
}
