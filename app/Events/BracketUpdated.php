<?php

namespace App\Events;

use App\Models\Tournament;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BracketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public array $updatedMatch
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("tournament.{$this->tournament->id}.bracket"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'bracket.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'tournament_id' => $this->tournament->id,
            'match' => $this->updatedMatch,
        ];
    }
}
