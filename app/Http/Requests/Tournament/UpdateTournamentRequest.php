<?php

namespace App\Http\Requests\Tournament;

use App\DTOs\UpdateTournamentDTO;
use Illuminate\Foundation\Http\FormRequest;

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

        // Can only update if tournament hasn't started yet
        // Check status directly, not isRegistrationOpen() which has date checks
        $editableStatuses = ['draft', 'pending_review', 'registration'];
        $isEditable = in_array($tournament->status->value, $editableStatuses);

        if (!$isEditable) {
            return []; // No updates allowed for active/completed/cancelled
        }

        $rules = [
            'name' => ['sometimes', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue_name' => ['nullable', 'string', 'max:150'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date'],
            'starts_at' => ['sometimes', 'date'],
        ];

        // Only allow config changes if tournament is still in draft or pending review
        if (in_array($tournament->status->value, ['draft', 'pending_review'])) {
            $rules['winners_count'] = ['sometimes', 'integer', 'min:1', 'max:10'];
            $rules['race_to'] = ['sometimes', 'integer', 'min:2', 'max:7'];
            $rules['finals_race_to'] = ['nullable', 'integer', 'min:2', 'max:9', 'gte:race_to'];
            $rules['confirmation_hours'] = ['sometimes', 'integer', 'min:1', 'max:72'];
            $rules['entry_fee'] = ['sometimes', 'integer', 'min:0'];
            $rules['entry_fee_currency'] = ['sometimes', 'string', 'size:3'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Tournament name must be at least 5 characters',
            'registration_closes_at.after' => 'Registration must close after it opens',
            'starts_at.after' => 'Tournament must start after registration closes',
            'race_to.min' => 'Race to must be at least 2',
            'race_to.max' => 'Race to cannot exceed 7',
            'finals_race_to.gte' => 'Finals race to must be greater than or equal to regular matches',
        ];
    }

    public function toDTO(): UpdateTournamentDTO
    {
        return UpdateTournamentDTO::fromArray($this->validated());
    }
}
