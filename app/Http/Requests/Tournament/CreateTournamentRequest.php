<?php

namespace App\Http\Requests\Tournament;

use App\DTOs\CreateTournamentDTO;
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
            'type' => ['sometimes', Rule::enum(TournamentType::class)],
            'geographic_scope_id' => ['required', 'exists:geographic_units,id'],

            // Venue
            'venue_name' => ['nullable', 'string', 'max:150'],
            'venue_address' => ['nullable', 'string', 'max:500'],

            // Dates
            'registration_opens_at' => ['nullable', 'date', 'after_or_equal:now'],
            'registration_closes_at' => ['required', 'date', 'after_or_equal:now'],
            'starts_at' => ['required', 'date', 'after_or_equal:registration_closes_at'],

            // Configuration
            'winners_count' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'winners_per_level' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'race_to' => ['sometimes', 'integer', 'min:2', 'max:7'],
            'finals_race_to' => ['nullable', 'integer', 'min:2', 'max:9', 'gte:race_to'],
            // Note: match_deadline_hours and confirmation_hours are fixed at 72h and 24h respectively

            // Entry fee
            'entry_fee' => ['sometimes', 'integer', 'min:0'],
            'entry_fee_currency' => ['sometimes', 'string', 'size:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tournament name is required',
            'name.min' => 'Tournament name must be at least 5 characters',
            'geographic_scope_id.required' => 'Please select a geographic scope',
            'geographic_scope_id.exists' => 'Invalid geographic scope selected',
            'registration_closes_at.after' => 'Registration must close after it opens',
            'starts_at.after' => 'Tournament must start after registration closes',
            'race_to.min' => 'Race to must be at least 2',
            'race_to.max' => 'Race to cannot exceed 7',
            'finals_race_to.gte' => 'Finals race to must be greater than or equal to regular matches',
        ];
    }

    public function toDTO(): CreateTournamentDTO
    {
        return CreateTournamentDTO::fromArray($this->validated());
    }
}
