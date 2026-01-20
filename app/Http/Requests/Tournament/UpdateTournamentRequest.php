<?php

namespace App\Http\Requests\Tournament;

use App\DTOs\UpdateTournamentDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tournament = $this->route('tournament');

        // Must be the creator or super admin
        return $this->user()->id === $tournament->created_by || $this->user()->is_super_admin;
    }

    public function rules(): array
    {
        $tournament = $this->route('tournament');

        // Can only update if tournament is still in draft or registration
        if (!$tournament->isDraft() && !$tournament->isRegistrationOpen()) {
            return []; // No updates allowed
        }

        $rules = [
            'name' => ['sometimes', 'string', 'min:5', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];

        // Only allow date changes if tournament hasn't started
        if ($tournament->isDraft() || $tournament->isRegistrationOpen()) {
            $rules['registration_opens_at'] = ['nullable', 'date'];
            $rules['registration_closes_at'] = ['sometimes', 'date', 'after:registration_opens_at'];
            $rules['starts_at'] = ['sometimes', 'date', 'after:registration_closes_at'];
        }

        // Only allow config changes if tournament is still in draft
        if ($tournament->isDraft()) {
            $rules['winners_count'] = ['sometimes', 'integer', 'min:1', 'max:10'];
            $rules['best_of'] = ['sometimes', 'integer', Rule::in([1, 3, 5, 7])];
            $rules['confirmation_hours'] = ['sometimes', 'integer', 'min:1', 'max:72'];
            $rules['min_players_for_groups'] = ['sometimes', 'integer', 'min:8', 'max:128'];
            $rules['players_per_group'] = ['sometimes', 'integer', 'min:3', 'max:8'];
            $rules['advance_per_group'] = ['sometimes', 'integer', 'min:1', 'max:4'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Tournament name must be at least 5 characters',
            'registration_closes_at.after' => 'Registration must close after it opens',
            'starts_at.after' => 'Tournament must start after registration closes',
            'best_of.in' => 'Best of must be 1, 3, 5, or 7',
        ];
    }

    public function toDTO(): UpdateTournamentDTO
    {
        return UpdateTournamentDTO::fromArray($this->validated());
    }
}
