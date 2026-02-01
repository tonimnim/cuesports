<?php

use App\Models\GameMatch;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('match.{matchId}', function ($user, $matchId) {
    $match = GameMatch::with(['player1.playerProfile', 'player2.playerProfile'])->find($matchId);

    if (!$match || !$user->playerProfile) {
        return false;
    }

    $playerProfileId = $user->playerProfile->id;

    // Check if user is player1
    if ($match->player1 && $match->player1->player_profile_id === $playerProfileId) {
        return true;
    }

    // Check if user is player2
    if ($match->player2 && $match->player2->player_profile_id === $playerProfileId) {
        return true;
    }

    return false;
});

Broadcast::channel('tournament.{tournamentId}', function ($user, $tournamentId) {
    // Allow any authenticated user to subscribe to tournament updates
    // In a more restrictive setup, you could check if they're a participant
    return $user !== null;
});
