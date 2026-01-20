<?php

namespace App\Services;

use App\DTOs\CreateTournamentDTO;
use App\DTOs\TournamentFiltersDTO;
use App\DTOs\UpdateTournamentDTO;
use App\Enums\ParticipantStatus;
use App\Enums\TournamentStatus;
use App\Events\ParticipantRegistered;
use App\Events\ParticipantWithdrawn;
use App\Events\TournamentCancelled;
use App\Events\TournamentCreated;
use App\Events\TournamentStarted;
use App\Models\PlayerProfile;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentService
{
    /**
     * Create a new tournament.
     */
    public function create(CreateTournamentDTO $dto, User $creator): Tournament
    {
        return DB::transaction(function () use ($dto, $creator) {
            $tournament = Tournament::create([
                ...$dto->toArray(),
                'status' => TournamentStatus::DRAFT,
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
     * Note: Bracket generation will be handled by BracketGeneratorService.
     */
    public function start(Tournament $tournament): Tournament
    {
        if ($tournament->isActive() || $tournament->isCompleted()) {
            throw new \InvalidArgumentException('Tournament has already started or completed.');
        }

        if ($tournament->participants_count < 2) {
            throw new \InvalidArgumentException('Need at least 2 participants to start.');
        }

        return DB::transaction(function () use ($tournament) {
            // Activate all registered participants
            $tournament->participants()
                ->where('status', ParticipantStatus::REGISTERED)
                ->update(['status' => ParticipantStatus::ACTIVE]);

            $tournament->status = TournamentStatus::ACTIVE;
            $tournament->starts_at = now();
            $tournament->save();

            event(new TournamentStarted($tournament));

            return $tournament;
        });
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

        // Check if all matches are completed
        $pendingMatches = $tournament->matches()
            ->whereNotIn('status', ['completed', 'cancelled', 'expired'])
            ->count();

        if ($pendingMatches > 0) {
            throw new \InvalidArgumentException("Cannot complete: {$pendingMatches} matches still pending.");
        }

        $tournament->complete();

        return $tournament;
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
     */
    public function getEligibleForPlayer(PlayerProfile $player, TournamentFiltersDTO $filters): LengthAwarePaginator
    {
        $playerUnitIds = $this->getPlayerScopeUnitIds($player);

        $query = Tournament::query()
            ->with(['geographicScope', 'createdBy.organizerProfile'])
            ->whereIn('geographic_scope_id', $playerUnitIds);

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
     */
    public function getByOrganizer(User $organizer, TournamentFiltersDTO $filters): LengthAwarePaginator
    {
        $filters = new TournamentFiltersDTO(
            ...(array) $filters,
            createdBy: $organizer->id,
        );

        return $this->getFiltered($filters);
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

        return [
            'can_start' => empty($issues),
            'issues' => $issues,
            'participants_count' => $tournament->participants_count,
        ];
    }
}
