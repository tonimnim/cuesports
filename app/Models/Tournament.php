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
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        'venue_name',
        'venue_address',
        'registration_opens_at',
        'registration_closes_at',
        'starts_at',
        'ends_at',
        'winners_count',
        'winners_per_level',
        'race_to',
        'finals_race_to',
        'match_deadline_hours',
        'confirmation_hours',
        'auto_confirm_results',
        'double_forfeit_on_expiry',
        'entry_fee',
        'entry_fee_currency',
        'requires_payment',
        'participants_count',
        'matches_count',
        'created_by',
        'verified_at',
        'verified_by',
        'rejection_reason',
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
        'race_to' => 'integer',
        'finals_race_to' => 'integer',
        'match_deadline_hours' => 'integer',
        'confirmation_hours' => 'integer',
        'auto_confirm_results' => 'boolean',
        'double_forfeit_on_expiry' => 'boolean',
        'entry_fee' => 'integer',
        'requires_payment' => 'boolean',
        'participants_count' => 'integer',
        'matches_count' => 'integer',
        'verified_at' => 'datetime',
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

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
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
        return $this->hasMany(GameMatch::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class, 'created_by', 'user_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    // Entry Fee Methods

    public function isFree(): bool
    {
        return !$this->requires_payment || $this->entry_fee === 0;
    }

    public function getFormattedEntryFeeAttribute(): string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        return $this->entry_fee_currency . ' ' . number_format($this->entry_fee / 100, 2);
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

    public function scopePendingReview($query)
    {
        return $query->where('status', TournamentStatus::PENDING_REVIEW);
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

    /**
     * Get the rating multiplier for this tournament.
     * Based on tournament type and geographic level.
     */
    public function getRatingMultiplier(): float
    {
        $geographicScope = $this->geographicScope;

        if (!$geographicScope) {
            return 1.0;
        }

        return $this->type->getRatingMultiplier($geographicScope->getLevelEnum());
    }

    // Status Checks

    public function isPendingReview(): bool
    {
        return $this->status === TournamentStatus::PENDING_REVIEW;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

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

    /**
     * Check if the scheduled start date has arrived (date comparison only, ignores time).
     */
    public function isStartDateReached(): bool
    {
        if (!$this->starts_at) {
            return true; // No date set, can start anytime
        }

        return now()->toDateString() >= $this->starts_at->toDateString();
    }

    /**
     * Check if the tournament can be started by the organizer.
     */
    public function canBeStarted(): bool
    {
        return $this->status === TournamentStatus::REGISTRATION
            && $this->participants_count >= 2
            && $this->isStartDateReached();
    }

    /**
     * Get the race_to value for a match.
     * Finals use finals_race_to if set, otherwise falls back to race_to.
     */
    public function getRaceToForMatch(bool $isFinal = false): int
    {
        if ($isFinal && $this->finals_race_to) {
            return $this->finals_race_to;
        }

        return $this->race_to ?? 3;
    }

    // Deadline Settings (Fixed values - not configurable by organizer)

    /**
     * Match deadline is always 3 days (72 hours).
     */
    public function getMatchDeadlineHours(): int
    {
        return 72; // Fixed: 3 days
    }

    /**
     * Confirmation deadline is always 24 hours.
     */
    public function getConfirmationHours(): int
    {
        return 24; // Fixed: 24 hours
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
        // Open tournaments (no geographic scope) - anyone can join
        if ($this->geographic_scope_id === null) {
            return true;
        }

        $playerUnit = $player->geographicUnit;
        $scopeUnit = $this->geographicScope;

        // Safety check if scope unit couldn't be loaded
        if (!$scopeUnit) {
            return true;
        }

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

    public function verify(User $verifier): void
    {
        $this->status = TournamentStatus::DRAFT;
        $this->verified_at = now();
        $this->verified_by = $verifier->id;
        $this->rejection_reason = null;
        $this->save();
    }

    public function reject(User $verifier, string $reason): void
    {
        $this->verified_by = $verifier->id;
        $this->rejection_reason = $reason;
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
