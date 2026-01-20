<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
                'description' => $this->type->description(),
            ],
            'format' => [
                'value' => $this->format->value,
                'label' => $this->format->label(),
                'description' => $this->format->description(),
                'has_group_stage' => $this->format->hasGroupStage(),
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'can_register' => $this->status->canRegister(),
                'can_play' => $this->status->canPlay(),
                'is_finished' => $this->status->isFinished(),
            ],
            'geographic_scope' => $this->whenLoaded('geographicScope', fn() => [
                'id' => $this->geographicScope->id,
                'name' => $this->geographicScope->name,
                'level' => $this->geographicScope->level,
                'local_term' => $this->geographicScope->local_term,
                'full_path' => $this->geographicScope->getFullPath(),
            ]),
            'organizer' => $this->whenLoaded('createdBy', fn() => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->organizerProfile?->organization_name ?? $this->createdBy->playerProfile?->display_name,
                'logo_url' => $this->createdBy->organizerProfile?->logo_url,
            ]),
            'dates' => [
                'registration_opens_at' => $this->registration_opens_at?->toISOString(),
                'registration_closes_at' => $this->registration_closes_at?->toISOString(),
                'starts_at' => $this->starts_at?->toISOString(),
                'ends_at' => $this->ends_at?->toISOString(),
                'is_registration_open' => $this->isRegistrationOpen(),
            ],
            'settings' => [
                'winners_count' => $this->winners_count,
                'winners_per_level' => $this->winners_per_level,
                'best_of' => $this->best_of,
                'confirmation_hours' => $this->confirmation_hours,
            ],
            'group_settings' => $this->when($this->format->hasGroupStage(), fn() => [
                'min_players_for_groups' => $this->min_players_for_groups,
                'players_per_group' => $this->players_per_group,
                'advance_per_group' => $this->advance_per_group,
                'should_use_groups' => $this->shouldUseGroups(),
                'groups_count' => $this->calculateGroupsCount(),
            ]),
            'stats' => [
                'participants_count' => $this->participants_count,
                'matches_count' => $this->matches_count,
            ],
            'current_stage' => $this->when($this->isSpecial(), fn() => $this->getCurrentStage() ? [
                'id' => $this->getCurrentStage()->id,
                'name' => $this->getCurrentStage()->name,
                'stage_number' => $this->getCurrentStage()->stage_number,
            ] : null),
            'user_participation' => $this->when(
                $this->relationLoaded('participants') && request()->user(),
                fn() => $this->getUserParticipation(request()->user())
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get the current user's participation status.
     */
    protected function getUserParticipation($user): ?array
    {
        if (!$user?->playerProfile) {
            return null;
        }

        $participant = $this->participants
            ->where('player_profile_id', $user->playerProfile->id)
            ->first();

        if (!$participant) {
            return [
                'is_registered' => false,
                'can_register' => $this->canPlayerRegister($user->playerProfile),
            ];
        }

        return [
            'is_registered' => true,
            'participant_id' => $participant->id,
            'status' => $participant->status->value,
            'seed' => $participant->seed,
            'stats' => [
                'matches_played' => $participant->matches_played,
                'matches_won' => $participant->matches_won,
                'frames_won' => $participant->frames_won,
                'frame_difference' => $participant->frame_difference,
            ],
        ];
    }
}
