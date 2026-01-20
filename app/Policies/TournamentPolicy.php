<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    /**
     * Anyone can view tournaments list.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Anyone can view a tournament.
     */
    public function view(?User $user, Tournament $tournament): bool
    {
        return true;
    }

    /**
     * Organizers can create tournaments.
     */
    public function create(User $user): bool
    {
        return $user->is_organizer && $user->organizerProfile?->canCreateTournaments();
    }

    /**
     * Creator or admin can update.
     */
    public function update(User $user, Tournament $tournament): bool
    {
        // Can't update completed or cancelled tournaments
        if ($tournament->isCompleted() || $tournament->isCancelled()) {
            return false;
        }

        return $user->id === $tournament->created_by || $user->is_super_admin;
    }

    /**
     * Creator or admin can delete (only drafts).
     */
    public function delete(User $user, Tournament $tournament): bool
    {
        // Can only delete drafts
        if (!$tournament->isDraft()) {
            return false;
        }

        return $user->id === $tournament->created_by || $user->is_super_admin;
    }

    /**
     * Creator or admin can manage participants.
     */
    public function manageParticipants(User $user, Tournament $tournament): bool
    {
        return $user->id === $tournament->created_by || $user->is_super_admin;
    }

    /**
     * Creator or admin can open registration.
     */
    public function openRegistration(User $user, Tournament $tournament): bool
    {
        if (!$tournament->isDraft()) {
            return false;
        }

        return $user->id === $tournament->created_by || $user->is_super_admin;
    }

    /**
     * Creator or admin can close registration.
     */
    public function closeRegistration(User $user, Tournament $tournament): bool
    {
        if (!$tournament->isRegistrationOpen()) {
            return false;
        }

        return $user->id === $tournament->created_by || $user->is_super_admin;
    }

    /**
     * Creator or admin can start tournament (generate brackets).
     */
    public function start(User $user, Tournament $tournament): bool
    {
        // Must have closed registration or have enough participants
        if ($tournament->isDraft() || $tournament->isActive()) {
            return false;
        }

        // Need at least 2 participants
        if ($tournament->participants_count < 2) {
            return false;
        }

        return $user->id === $tournament->created_by || $user->is_super_admin;
    }

    /**
     * Creator or admin can cancel.
     */
    public function cancel(User $user, Tournament $tournament): bool
    {
        // Can't cancel already finished
        if ($tournament->isCompleted() || $tournament->isCancelled()) {
            return false;
        }

        return $user->id === $tournament->created_by || $user->is_super_admin;
    }

    /**
     * Players can register for tournaments.
     */
    public function register(User $user, Tournament $tournament): bool
    {
        if (!$user->is_player || !$user->playerProfile) {
            return false;
        }

        return $tournament->canPlayerRegister($user->playerProfile);
    }

    /**
     * Players can withdraw from tournaments.
     */
    public function withdraw(User $user, Tournament $tournament): bool
    {
        if (!$user->is_player || !$user->playerProfile) {
            return false;
        }

        // Can only withdraw during registration phase
        if (!$tournament->isDraft() && !$tournament->isRegistrationOpen()) {
            return false;
        }

        // Must be registered
        return $tournament->participants()
            ->where('player_profile_id', $user->playerProfile->id)
            ->exists();
    }
}
