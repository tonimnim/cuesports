<?php

namespace App\Services;

use App\DTOs\CreateTournamentDTO;
use App\DTOs\TournamentFiltersDTO;
use App\DTOs\UpdateTournamentDTO;
use App\Enums\ParticipantStatus;
use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use App\Events\ParticipantRegistered;
use App\Events\ParticipantWithdrawn;
use App\Events\TournamentCancelled;
use App\Events\TournamentCreated;
use App\Events\TournamentStarted;
use App\Models\GeographicUnit;
use App\Models\PlayerProfile;
use App\Models\Tournament;
use App\Models\TournamentLevelSetting;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Services\Bracket\BracketService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentService
{
    public function __construct(
        protected BracketService $bracketService
    ) {}
    /**
     * Create a new tournament.
     */
    public function create(CreateTournamentDTO $dto, User $creator): Tournament
    {
        return DB::transaction(function () use ($dto, $creator) {
            $data = $dto->toArray();

            // For Special tournaments, apply default race_to settings from admin config
            // if organizer hasn't specified their own values
            if ($dto->type === TournamentType::SPECIAL) {
                $geographicUnit = GeographicUnit::find($dto->geographicScopeId);
                if ($geographicUnit) {
                    $level = $geographicUnit->getLevelEnum();

                    // Only override if using default values (race_to = 3)
                    if ($dto->raceTo === 3) {
                        $data['race_to'] = TournamentLevelSetting::getDefaultRaceTo($level);
                    }

                    // Set finals_race_to from admin config if not specified
                    if ($dto->finalsRaceTo === null) {
                        $data['finals_race_to'] = TournamentLevelSetting::getDefaultFinalsRaceTo($level);
                    }
                }
            }

            $tournament = Tournament::create([
                ...$data,
                'status' => TournamentStatus::PENDING_REVIEW,
                'created_by' => $creator->id,
            ]);

            // Increment organizer's tournament count
            if ($creator->organizerProfile) {
                $creator->organizerProfile->incrementTournamentsHosted();
            }

            event(new TournamentCreated($tournament));

            return $tournament->load(['geographicScope', 'createdBy']);
        });
    }

    /**
     * Update a tournament.
     */
    public function update(Tournament $tournament, UpdateTournamentDTO $dto): Tournament
    {
        if (!$dto->hasChanges()) {
            return $tournament;
        }

        $tournament->update($dto->toArray());

        return $tournament->fresh(['geographicScope', 'createdBy']);
    }

    /**
     * Delete a tournament (only drafts).
     */
    public function delete(Tournament $tournament): bool
    {
        if (!$tournament->isDraft()) {
            throw new \InvalidArgumentException('Only draft tournaments can be deleted.');
        }

        return $tournament->delete();
    }

    /**
     * Open registration for a tournament.
     */
    public function openRegistration(Tournament $tournament): Tournament
    {
        if (!$tournament->isDraft()) {
            throw new \InvalidArgumentException('Can only open registration for draft tournaments.');
        }

        $tournament->status = TournamentStatus::REGISTRATION;
        $tournament->registration_opens_at = $tournament->registration_opens_at ?? now();
        $tournament->save();

        return $tournament;
    }

    /**
     * Close registration for a tournament.
     */
    public function closeRegistration(Tournament $tournament): Tournament
    {
        if (!$tournament->isRegistrationOpen()) {
            throw new \InvalidArgumentException('Tournament registration is not open.');
        }

        $tournament->registration_closes_at = now();
        $tournament->save();

        return $tournament;
    }

    /**
     * Start a tournament (after registration closes).
     * Generates the bracket and activates participants.
     * Can only be called on or after the scheduled start date.
     */
    public function start(Tournament $tournament): Tournament
    {
        if ($tournament->isActive() || $tournament->isCompleted()) {
            throw new \InvalidArgumentException('Tournament has already started or completed.');
        }

        if ($tournament->participants_count < 2) {
            throw new \InvalidArgumentException('Need at least 2 participants to start.');
        }

        // Validate that we're on or after the scheduled start date
        if ($tournament->starts_at && now()->toDateString() < $tournament->starts_at->toDateString()) {
            throw new \InvalidArgumentException(
                'Cannot start before the scheduled date (' . $tournament->starts_at->toDateString() . ').'
            );
        }

        return DB::transaction(function () use ($tournament) {
            // Generate the bracket using the new fair-match bracket service
            $result = $this->bracketService->generate($tournament);

            // Activate all participants
            $tournament->participants()->update([
                'status' => ParticipantStatus::ACTIVE,
            ]);

            // Update tournament
            $tournament->status = TournamentStatus::ACTIVE;
            $tournament->matches_count = $result->matchesCreated;
            $tournament->save();

            event(new TournamentStarted($tournament));

            return $tournament->fresh(['participants', 'matches']);
        });
    }

    /**
     * Get bracket visualization data.
     */
    public function getBracket(Tournament $tournament): array
    {
        return $this->bracketService->getBracketData($tournament);
    }

    /**
     * Cancel a tournament.
     */
    public function cancel(Tournament $tournament, ?string $reason = null): Tournament
    {
        if ($tournament->isCompleted() || $tournament->isCancelled()) {
            throw new \InvalidArgumentException('Tournament is already finished.');
        }

        return DB::transaction(function () use ($tournament, $reason) {
            $tournament->cancel();

            event(new TournamentCancelled($tournament, $reason));

            return $tournament;
        });
    }

    /**
     * Complete a tournament.
     */
    public function complete(Tournament $tournament): Tournament
    {
        if (!$tournament->isActive()) {
            throw new \InvalidArgumentException('Can only complete active tournaments.');
        }

        // Check if bracket is complete
        if (!$this->bracketService->isBracketComplete($tournament)) {
            $pendingMatches = $tournament->matches()
                ->whereNotIn('status', ['completed', 'cancelled', 'expired'])
                ->count();
            throw new \InvalidArgumentException("Cannot complete: {$pendingMatches} matches still pending.");
        }

        return DB::transaction(function () use ($tournament) {
            // Calculate and assign final positions
            $this->bracketService->calculateFinalPositions($tournament);

            $tournament->complete();

            return $tournament;
        });
    }

    /**
     * Register a player for a tournament.
     */
    public function registerPlayer(Tournament $tournament, PlayerProfile $player): TournamentParticipant
    {
        if (!$tournament->canPlayerRegister($player)) {
            throw new \InvalidArgumentException('Player cannot register for this tournament.');
        }

        return DB::transaction(function () use ($tournament, $player) {
            $participant = TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'player_profile_id' => $player->id,
                'status' => ParticipantStatus::REGISTERED,
                'registered_at' => now(),
            ]);

            $tournament->incrementParticipantsCount();

            event(new ParticipantRegistered($tournament, $participant));

            return $participant->load('playerProfile');
        });
    }

    /**
     * Withdraw a player from a tournament.
     */
    public function withdrawPlayer(Tournament $tournament, PlayerProfile $player): bool
    {
        $participant = $tournament->participants()
            ->where('player_profile_id', $player->id)
            ->first();

        if (!$participant) {
            throw new \InvalidArgumentException('Player is not registered for this tournament.');
        }

        // Can only withdraw before tournament starts
        if ($tournament->isActive() || $tournament->isCompleted()) {
            throw new \InvalidArgumentException('Cannot withdraw after tournament has started.');
        }

        return DB::transaction(function () use ($tournament, $participant) {
            $participant->delete();
            $tournament->decrementParticipantsCount();

            event(new ParticipantWithdrawn($tournament, $participant));

            return true;
        });
    }

    /**
     * Remove a participant (admin action).
     */
    public function removeParticipant(Tournament $tournament, TournamentParticipant $participant): bool
    {
        return DB::transaction(function () use ($tournament, $participant) {
            if ($tournament->isActive()) {
                // If tournament is active, disqualify instead of delete
                $participant->disqualify();
            } else {
                $participant->delete();
                $tournament->decrementParticipantsCount();
            }

            return true;
        });
    }

    /**
     * Get tournaments with filters.
     */
    public function getFiltered(TournamentFiltersDTO $filters): LengthAwarePaginator
    {
        $query = Tournament::query()
            ->with(['geographicScope', 'createdBy.organizerProfile']);

        $this->applyFilters($query, $filters);

        return $query
            ->orderBy($filters->sortBy, $filters->sortDirection)
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Get tournaments for a specific player (eligible tournaments).
     * Includes: Open tournaments (null scope) + tournaments matching player's geographic scope
     */
    public function getEligibleForPlayer(PlayerProfile $player, TournamentFiltersDTO $filters): LengthAwarePaginator
    {
        $playerUnitIds = $this->getPlayerScopeUnitIds($player);

        $query = Tournament::query()
            ->with(['geographicScope', 'createdBy.organizerProfile'])
            ->where(function ($q) use ($playerUnitIds) {
                // Open tournaments (no geographic restriction)
                $q->whereNull('geographic_scope_id')
                    // OR tournaments matching player's geographic scope
                    ->orWhereIn('geographic_scope_id', $playerUnitIds);
            });

        // Default to registration open
        if (!$filters->status) {
            $query->registrationOpen();
        }

        $this->applyFilters($query, $filters);

        return $query
            ->orderBy($filters->sortBy, $filters->sortDirection)
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Get tournaments created by an organizer.
     * Shows ALL statuses (including pending_review, draft) so organizers can manage their tournaments.
     */
    public function getByOrganizer(User $organizer, TournamentFiltersDTO $filters): LengthAwarePaginator
    {
        $query = Tournament::query()
            ->with(['geographicScope', 'createdBy.organizerProfile'])
            ->where('created_by', $organizer->id);

        // Apply type filter if specified
        if ($filters->type) {
            $query->where('type', $filters->type);
        }

        // Apply status filter if specified, otherwise show ALL statuses for organizer
        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        // Apply format filter if specified
        if ($filters->format) {
            $query->where('format', $filters->format);
        }

        // Apply search filter if specified
        if ($filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhere('description', 'ILIKE', "%{$filters->search}%");
            });
        }

        return $query
            ->orderBy($filters->sortBy, $filters->sortDirection)
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Get tournaments a player has registered for.
     */
    public function getRegisteredByPlayer(PlayerProfile $player, TournamentFiltersDTO $filters): LengthAwarePaginator
    {
        $query = Tournament::query()
            ->with(['geographicScope', 'createdBy.organizerProfile'])
            ->whereHas('participants', function ($q) use ($player) {
                $q->where('player_profile_id', $player->id);
            });

        // Don't apply default status filter for my-registered - show all statuses
        if ($filters->type) {
            $query->where('type', $filters->type);
        }

        if ($filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhere('description', 'ILIKE', "%{$filters->search}%");
            });
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Get tournament participants.
     */
    public function getParticipants(Tournament $tournament, string $sortBy = 'registered_at'): Collection
    {
        $validSorts = ['registered_at', 'seed', 'points', 'frame_difference'];
        $sortBy = in_array($sortBy, $validSorts) ? $sortBy : 'registered_at';

        return $tournament->participants()
            ->with('playerProfile.geographicUnit')
            ->orderByDesc($sortBy)
            ->get();
    }

    /**
     * Get tournament standings/leaderboard.
     */
    public function getStandings(Tournament $tournament): Collection
    {
        return $tournament->getRankedParticipants();
    }

    /**
     * Apply filters to tournament query.
     */
    protected function applyFilters(Builder $query, TournamentFiltersDTO $filters): void
    {
        if ($filters->type) {
            $query->where('type', $filters->type);
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        } else {
            // By default, only show publicly visible tournaments
            $query->whereIn('status', [
                TournamentStatus::REGISTRATION,
                TournamentStatus::ACTIVE,
                TournamentStatus::COMPLETED,
            ]);
        }

        if ($filters->format) {
            $query->where('format', $filters->format);
        }

        if ($filters->geographicScopeId) {
            $query->where('geographic_scope_id', $filters->geographicScopeId);
        }

        if ($filters->createdBy) {
            $query->where('created_by', $filters->createdBy);
        }

        if ($filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhere('description', 'ILIKE', "%{$filters->search}%");
            });
        }

        if ($filters->registrationOpen) {
            $query->registrationOpen();
        }
    }

    /**
     * Get geographic unit IDs for player's scope (their unit and all ancestors).
     */
    protected function getPlayerScopeUnitIds(PlayerProfile $player): array
    {
        $unit = $player->geographicUnit;
        $ids = [$unit->id];

        foreach ($unit->getAncestors() as $ancestor) {
            $ids[] = $ancestor->id;
        }

        return $ids;
    }

    /**
     * Check if tournament can be started.
     */
    public function canStart(Tournament $tournament): array
    {
        $issues = [];

        if ($tournament->isActive() || $tournament->isCompleted()) {
            $issues[] = 'Tournament has already started or completed.';
        }

        if ($tournament->participants_count < 2) {
            $issues[] = 'Need at least 2 participants.';
        }

        if ($tournament->isRegistrationOpen() && $tournament->registration_closes_at->isFuture()) {
            $issues[] = 'Registration is still open.';
        }

        // Check if current date is on or after the scheduled start date
        if ($tournament->starts_at && now()->toDateString() < $tournament->starts_at->toDateString()) {
            $issues[] = 'Cannot start before the scheduled date (' . $tournament->starts_at->toDateString() . ').';
        }

        return [
            'can_start' => empty($issues),
            'issues' => $issues,
            'participants_count' => $tournament->participants_count,
            'scheduled_start_date' => $tournament->starts_at?->toDateString(),
        ];
    }
}
