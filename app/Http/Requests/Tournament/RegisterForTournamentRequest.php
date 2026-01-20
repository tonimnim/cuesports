<?php

namespace App\Http\Requests\Tournament;

use Illuminate\Foundation\Http\FormRequest;

class RegisterForTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $tournament = $this->route('tournament');

        // Must be a player with a profile
        if (!$user->is_player || !$user->playerProfile) {
            return false;
        }

        // Tournament must be open for registration
        if (!$tournament->isRegistrationOpen()) {
            return false;
        }

        // Player must be eligible for this tournament
        return $tournament->canPlayerRegister($user->playerProfile);
    }

    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization()
    {
        $user = $this->user();
        $tournament = $this->route('tournament');

        $message = 'You cannot register for this tournament.';

        if (!$user->is_player || !$user->playerProfile) {
            $message = 'You must have a player profile to register.';
        } elseif (!$tournament->isRegistrationOpen()) {
            $message = 'Tournament registration is not open.';
        } elseif ($tournament->participants()->where('player_profile_id', $user->playerProfile->id)->exists()) {
            $message = 'You are already registered for this tournament.';
        } elseif (!$tournament->isPlayerEligible($user->playerProfile)) {
            $message = 'You are not eligible for this tournament based on your location.';
        }

        throw new \Illuminate\Auth\Access\AuthorizationException($message);
    }
}
