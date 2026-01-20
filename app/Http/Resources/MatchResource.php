<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_id' => $this->tournament_id,
            'tournament' => $this->whenLoaded('tournament', fn() => [
                'id' => $this->tournament->id,
                'name' => $this->tournament->name,
                'type' => $this->tournament->type->value,
            ]),
            'stage_id' => $this->stage_id,
            'round' => [
                'number' => $this->round_number,
                'name' => $this->round_name,
            ],
            'match_type' => $this->match_type->value,
            'match_type_label' => $this->match_type->label(),
            'bracket_position' => $this->bracket_position,
            'player1' => $this->whenLoaded('player1', fn() => [
                'id' => $this->player1->id,
                'name' => $this->player1->playerProfile?->display_name,
                'rating' => $this->player1->playerProfile?->rating,
                'score' => $this->player1_score,
                'is_winner' => $this->winner_id === $this->player1_id,
            ]),
            'player2' => $this->whenLoaded('player2', fn() => $this->player2 ? [
                'id' => $this->player2->id,
                'name' => $this->player2->playerProfile?->display_name,
                'rating' => $this->player2->playerProfile?->rating,
                'score' => $this->player2_score,
                'is_winner' => $this->winner_id === $this->player2_id,
            ] : null),
            'score' => $this->getScoreDisplay(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'winner_id' => $this->winner_id,
            'submission' => [
                'submitted_by' => $this->submitted_by,
                'submitted_at' => $this->submitted_at?->toISOString(),
                'confirmed_by' => $this->confirmed_by,
                'confirmed_at' => $this->confirmed_at?->toISOString(),
            ],
            'dispute' => $this->when($this->isDisputed(), fn() => [
                'disputed_by' => $this->disputed_by,
                'disputed_at' => $this->disputed_at?->toISOString(),
                'reason' => $this->dispute_reason,
                'resolved_by' => $this->resolved_by,
                'resolved_at' => $this->resolved_at?->toISOString(),
                'resolution_notes' => $this->resolution_notes,
            ]),
            'timing' => [
                'scheduled_play_date' => $this->scheduled_play_date?->toISOString(),
                'played_at' => $this->played_at?->toISOString(),
                'expires_at' => $this->expires_at?->toISOString(),
                'time_remaining' => $this->getTimeRemaining(),
            ],
            'next_match' => $this->when($this->next_match_id, fn() => [
                'id' => $this->next_match_id,
                'slot' => $this->next_match_slot,
            ]),
            'group_number' => $this->group_number,
        ];
    }
}
