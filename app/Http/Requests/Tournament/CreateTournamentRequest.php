<?php

namespace App\Http\Requests\Tournament;

use App\DTOs\CreateTournamentDTO;
use App\Enums\TournamentFormat;
use App\Enums\TournamentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Must be an organizer
        return $this->user()->is_organizer && $this->user()->organizerProfile?->canCreateTournaments();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(TournamentType::class)],
            'format' => ['required', Rule::enum(TournamentFormat::class)],
            'geographic_scope_id' => ['required', 'exists:geographic_units,id'],

            // Dates
            'registration_opens_at' => ['nullable', 'date', 'after_or_equal:now'],
            'registration_closes_at' => ['required', 'date', 'after:registration_opens_at', 'after:now'],
            'starts_at' => ['required', 'date', 'after:registration_closes_at'],

            // Configuration
            'winners_count' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'winners_per_level' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'best_of' => ['sometimes', 'integer', Rule::in([1, 3, 5, 7])],
            'confirmation_hours' => ['sometimes', 'integer', 'min:1', 'max:72'],

            // Group stage config
            'min_players_for_groups' => ['sometimes', 'integer', 'min:8', 'max:128'],
            'players_per_group' => ['sometimes', 'integer', 'min:3', 'max:8'],
            'advance_per_group' => ['sometimes', 'integer', 'min:1', 'max:4', 'lt:players_per_group'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tournament name is required',
            'name.min' => 'Tournament name must be at least 5 characters',
            'type.required' => 'Tournament type is required',
            'format.required' => 'Tournament format is required',
            'geographic_scope_id.required' => 'Please select a geographic scope',
            'geographic_scope_id.exists' => 'Invalid geographic scope selected',
            'registration_closes_at.after' => 'Registration must close after it opens',
            'starts_at.after' => 'Tournament must start after registration closes',
            'best_of.in' => 'Best of must be 1, 3, 5, or 7',
            'advance_per_group.lt' => 'Players advancing must be less than players per group',
        ];
    }

    public function toDTO(): CreateTournamentDTO
    {
        return CreateTournamentDTO::fromArray($this->validated());
    }
}
