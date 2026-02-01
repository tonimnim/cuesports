<?php

namespace App\Events;

use App\Models\GameMatch as MatchModel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchDisputed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MatchModel $match
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tournament.{$this->match->tournament_id}"),
            new PrivateChannel('support.disputes'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'match.disputed';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'tournament_id' => $this->match->tournament_id,
            'tournament_name' => $this->match->tournament->name,
            'round_name' => $this->match->round_name,
            'player1' => [
                'id' => $this->match->player1?->id,
                'name' => $this->match->player1?->playerProfile->display_name,
            ],
            'player2' => [
                'id' => $this->match->player2?->id,
                'name' => $this->match->player2?->playerProfile->display_name,
            ],
            'disputed_by' => $this->match->disputed_by,
            'dispute_reason' => $this->match->dispute_reason,
            'disputed_at' => $this->match->disputed_at?->toISOString(),
        ];
    }
}
