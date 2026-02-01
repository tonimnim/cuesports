<?php

namespace App\Events;

use App\Models\GameMatch as MatchModel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchResolved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MatchModel $match
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tournament.{$this->match->tournament_id}"),
            new Channel("tournament.{$this->match->tournament_id}.public"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'match.resolved';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'tournament_id' => $this->match->tournament_id,
            'round_name' => $this->match->round_name,
            'player1' => [
                'id' => $this->match->player1?->id,
                'name' => $this->match->player1?->playerProfile->display_name,
                'score' => $this->match->player1_score,
            ],
            'player2' => [
                'id' => $this->match->player2?->id,
                'name' => $this->match->player2?->playerProfile->display_name,
                'score' => $this->match->player2_score,
            ],
            'winner' => [
                'id' => $this->match->winner?->id,
                'name' => $this->match->winner?->playerProfile->display_name,
            ],
            'resolution_notes' => $this->match->resolution_notes,
            'resolved_at' => $this->match->resolved_at?->toISOString(),
        ];
    }
}
