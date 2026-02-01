<?php

namespace App\Policies;

use App\Enums\TournamentType;
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
     * Only organizers can create Regular tournaments.
     * Only admin can create Special tournaments.
     */
    public function create(User $user): bool
    {
        // Admin can create (Special tournaments)
        if ($user->is_super_admin) {
            return true;
        }

        // Organizers can create (Regular tournaments)
        return $user->is_organizer && $user->organizerProfile?->canCreateTournaments();
    }

    /**
     * Check if user can create a Regular tournament.
     */
    public function createRegular(User $user): bool
    {
        return $user->is_organizer && $user->organizerProfile?->canCreateTournaments();
    }

    /**
     * Check if user can create a Special tournament.
     * Only Admin can create Special tournaments.
     */
    public function createSpecial(User $user): bool
    {
        return $user->is_super_admin;
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

        // Admin can update any tournament
        if ($user->is_super_admin) {
            return true;
        }

        // Creator can update their own (Regular only, Draft/Registration only)
        if ($user->id === $tournament->created_by) {
            return $tournament->isDraft() || $tournament->isRegistrationOpen();
        }

        return false;
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
     * Only Admin can approve tournaments (move from Pending Review to Draft).
     */
    public function approve(User $user, Tournament $tournament): bool
    {
        if (!$tournament->isPendingReview()) {
            return false;
        }

        return $user->is_super_admin;
    }

    /**
     * Only Admin can reject tournaments.
     */
    public function reject(User $user, Tournament $tournament): bool
    {
        if (!$tournament->isPendingReview()) {
            return false;
        }

        return $user->is_super_admin;
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
        if ($tournament->isDraft() || $tournament->isActive() || $tournament->isCompleted()) {
            return false;
        }

        // Need at least 2 participants
        if ($tournament->participants_count < 2) {
            return false;
        }

        // Can only start on or after the scheduled start date
        if ($tournament->starts_at && now()->toDateString() < $tournament->starts_at->toDateString()) {
            return false;
        }

        return $user->id === $tournament->created_by || $user->is_super_admin;
    }

    /**
     * Cancel tournament with role-based permissions:
     * - Admin: Can cancel both Regular and Special
     * - Support: Can cancel Regular only
     * - Organizer: Can cancel own Regular before start date
     */
    public function cancel(User $user, Tournament $tournament): bool
    {
        // Can't cancel already finished
        if ($tournament->isCompleted() || $tournament->isCancelled()) {
            return false;
        }

        // Admin can cancel any tournament
        if ($user->is_super_admin) {
            return true;
        }

        // Support can cancel Regular tournaments only
        if ($user->is_support && $tournament->isRegular()) {
            return true;
        }

        // Organizer can cancel their own Regular tournament before start date
        if ($user->id === $tournament->created_by && $tournament->isRegular()) {
            // Can only cancel before the tournament has started
            if ($tournament->isActive()) {
                return false;
            }
            return true;
        }

        return false;
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

    /**
     * Admin and Support can view all tournaments in admin panel.
     */
    public function viewAdmin(User $user): bool
    {
        return $user->is_super_admin || $user->is_support;
    }
}
