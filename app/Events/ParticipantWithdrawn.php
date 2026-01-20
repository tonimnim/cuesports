<?php

namespace App\Events;

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantWithdrawn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public TournamentParticipant $participant
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('tournament.' . $this->tournament->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'participant.withdrawn';
    }

    public function broadcastWith(): array
    {
        return [
            'participant_id' => $this->participant->id,
            'participants_count' => $this->tournament->fresh()->participants_count,
        ];
    }
}
