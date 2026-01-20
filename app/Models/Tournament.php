<?php

namespace App\Models;

use App\Enums\GeographicLevel;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'status',
        'format',
        'geographic_scope_id',
        'registration_opens_at',
        'registration_closes_at',
        'starts_at',
        'ends_at',
        'winners_count',
        'winners_per_level',
        'best_of',
        'confirmation_hours',
        'min_players_for_groups',
        'players_per_group',
        'advance_per_group',
        'participants_count',
        'matches_count',
        'created_by',
    ];

    protected $casts = [
        'type' => TournamentType::class,
        'status' => TournamentStatus::class,
        'format' => TournamentFormat::class,
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'winners_count' => 'integer',
        'winners_per_level' => 'integer',
        'best_of' => 'integer',
        'confirmation_hours' => 'integer',
        'min_players_for_groups' => 'integer',
        'players_per_group' => 'integer',
        'advance_per_group' => 'integer',
        'participants_count' => 'integer',
        'matches_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tournament) {
            if (empty($tournament->slug)) {
                $tournament->slug = Str::slug($tournament->name) . '-' . Str::random(6);
            }
        });
    }

    // Relationships

    public function geographicScope(): BelongsTo
    {
        return $this->belongsTo(GeographicUnit::class, 'geographic_scope_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(TournamentStage::class)->orderBy('stage_order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Match::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class, 'created_by', 'user_id');
    }

    // Scopes

    public function scopeRegular($query)
    {
        return $query->where('type', TournamentType::REGULAR);
    }

    public function scopeSpecial($query)
    {
        return $query->where('type', TournamentType::SPECIAL);
    }

    public function scopeStatus($query, TournamentStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRegistrationOpen($query)
    {
        return $query->where('status', TournamentStatus::REGISTRATION)
            ->where('registration_closes_at', '>', now());
    }

    public function scopeActive($query)
    {
        return $query->where('status', TournamentStatus::ACTIVE);
    }

    public function scopeInGeographicScope($query, int $geographicUnitId)
    {
        return $query->where('geographic_scope_id', $geographicUnitId);
    }

    // Type Checks

    public function isRegular(): bool
    {
        return $this->type === TournamentType::REGULAR;
    }

    public function isSpecial(): bool
    {
        return $this->type === TournamentType::SPECIAL;
    }

    // Status Checks

    public function isDraft(): bool
    {
        return $this->status === TournamentStatus::DRAFT;
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === TournamentStatus::REGISTRATION
            && $this->registration_closes_at->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === TournamentStatus::ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === TournamentStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === TournamentStatus::CANCELLED;
    }

    // Format Checks

    public function hasGroupStage(): bool
    {
        return $this->format->hasGroupStage();
    }

    public function shouldUseGroups(): bool
    {
        return $this->hasGroupStage() && $this->participants_count >= $this->min_players_for_groups;
    }

    // Helper Methods

    public function canPlayerRegister(PlayerProfile $player): bool
    {
        if (!$this->isRegistrationOpen()) {
            return false;
        }

        // Check if player is already registered
        if ($this->participants()->where('player_profile_id', $player->id)->exists()) {
            return false;
        }

        // Check geographic eligibility
        return $this->isPlayerEligible($player);
    }

    public function isPlayerEligible(PlayerProfile $player): bool
    {
        $playerUnit = $player->geographicUnit;
        $scopeUnit = $this->geographicScope;

        // Player must be within the tournament's geographic scope
        // i.e., player's unit must be the same as or a descendant of the scope unit
        if ($playerUnit->id === $scopeUnit->id) {
            return true;
        }

        // Check if player's unit is a descendant of the scope
        $current = $playerUnit;
        while ($current->parent_id !== null) {
            if ($current->parent_id === $scopeUnit->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    public function getActiveParticipants()
    {
        return $this->participants()
            ->whereIn('status', ['registered', 'active'])
            ->with('playerProfile')
            ->get();
    }

    public function getRankedParticipants()
    {
        return $this->participants()
            ->orderByDesc('points')
            ->orderByDesc('frame_difference')
            ->orderByDesc('frames_won')
            ->with('playerProfile')
            ->get();
    }

    public function getWinners()
    {
        return $this->participants()
            ->whereNotNull('final_position')
            ->orderBy('final_position')
            ->limit($this->winners_count)
            ->with('playerProfile')
            ->get();
    }

    public function getCurrentStage(): ?TournamentStage
    {
        if (!$this->isSpecial()) {
            return null;
        }

        return $this->stages()
            ->where('status', 'active')
            ->first();
    }

    public function calculateGroupsCount(): int
    {
        if (!$this->shouldUseGroups()) {
            return 0;
        }

        return (int) ceil($this->participants_count / $this->players_per_group);
    }

    // Status Transitions

    public function openRegistration(): void
    {
        $this->status = TournamentStatus::REGISTRATION;
        $this->save();
    }

    public function closeRegistration(): void
    {
        // Don't change status here - just close registration
        // The bracket generation will set status to ACTIVE
    }

    public function activate(): void
    {
        $this->status = TournamentStatus::ACTIVE;
        $this->save();
    }

    public function complete(): void
    {
        $this->status = TournamentStatus::COMPLETED;
        $this->ends_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = TournamentStatus::CANCELLED;
        $this->ends_at = now();
        $this->save();
    }

    // Stats Update

    public function incrementParticipantsCount(): void
    {
        $this->increment('participants_count');
    }

    public function decrementParticipantsCount(): void
    {
        $this->decrement('participants_count');
    }

    public function incrementMatchesCount(): void
    {
        $this->increment('matches_count');
    }
}
