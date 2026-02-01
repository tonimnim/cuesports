<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\GameMatch;
use App\Models\Notification;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to a single user.
     */
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $icon = null,
        ?string $actionUrl = null,
        ?string $actionText = null,
        ?array $data = null
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'action_url' => $actionUrl,
            'action_text' => $actionText,
            'data' => $data,
        ]);
    }

    /**
     * Send a notification to multiple users (bulk insert for performance).
     */
    public function sendBulk(
        Collection $users,
        string $type,
        string $title,
        string $message,
        ?string $icon = null,
        ?string $actionUrl = null,
        ?string $actionText = null,
        ?array $data = null
    ): int {
        if ($users->isEmpty()) {
            return 0;
        }

        $now = now();
        $notifications = $users->map(fn(User $user) => [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'action_url' => $actionUrl,
            'action_text' => $actionText,
            'data' => $data ? json_encode($data) : null,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        // Chunk insert for very large sets
        $chunks = array_chunk($notifications, 500);
        $inserted = 0;

        foreach ($chunks as $chunk) {
            Notification::insert($chunk);
            $inserted += count($chunk);
        }

        Log::info("Bulk notification sent to {$inserted} users", [
            'type' => $type,
            'title' => $title,
        ]);

        return $inserted;
    }

    /**
     * Get unread notifications for a user.
     */
    public function getUnread(User $user, int $limit = 50): Collection
    {
        return $user->inAppNotifications()
            ->unread()
            ->limit($limit)
            ->get();
    }

    /**
     * Get all notifications for a user with pagination.
     */
    public function getAll(User $user, int $perPage = 20)
    {
        return $user->inAppNotifications()->paginate($perPage);
    }

    /**
     * Get unread count for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Delete old read notifications (cleanup).
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return Notification::where('read_at', '<=', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Create a notification using NotificationType enum.
     */
    public function notify(
        User $user,
        NotificationType $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = []
    ): Notification {
        return $this->send(
            $user,
            $type->value,
            $title,
            $message,
            null,
            $actionUrl,
            null,
            $data
        );
    }

    /**
     * Send match scheduled notification.
     */
    public function sendMatchScheduled(User $user, GameMatch $match): void
    {
        $opponent = $this->getOpponentName($user, $match);
        $deadline = $match->expires_at?->format('M j, Y g:i A') ?? 'TBD';

        $this->notify(
            $user,
            NotificationType::MATCH_SCHEDULED,
            'New Match Scheduled',
            "You have a match against {$opponent}. Complete by {$deadline}.",
            "/player/matches/{$match->id}",
            [
                'match_id' => $match->id,
                'tournament_id' => $match->tournament_id,
                'opponent' => $opponent,
                'deadline' => $match->expires_at?->toISOString(),
            ]
        );
    }

    /**
     * Send match reminder notification.
     */
    public function sendMatchReminder(User $user, GameMatch $match, int $hoursRemaining): void
    {
        $opponent = $this->getOpponentName($user, $match);
        $type = $hoursRemaining === 24
            ? NotificationType::MATCH_REMINDER_24H
            : NotificationType::MATCH_REMINDER_48H;

        $this->notify(
            $user,
            $type,
            "{$hoursRemaining} Hours Remaining",
            "Your match against {$opponent} must be completed within {$hoursRemaining} hours.",
            "/player/matches/{$match->id}",
            [
                'match_id' => $match->id,
                'hours_remaining' => $hoursRemaining,
            ]
        );
    }

    /**
     * Send result submitted notification to opponent.
     */
    public function sendResultSubmitted(User $opponent, GameMatch $match, string $submitterName, string $score): void
    {
        $this->notify(
            $opponent,
            NotificationType::MATCH_RESULT_SUBMITTED,
            'Result Awaiting Confirmation',
            "{$submitterName} submitted result: {$score}. Please confirm or dispute.",
            "/player/matches/{$match->id}",
            [
                'match_id' => $match->id,
                'score' => $score,
                'confirmation_deadline' => $match->expires_at?->toISOString(),
            ]
        );
    }

    /**
     * Send result confirmed notification.
     */
    public function sendResultConfirmed(User $user, GameMatch $match, bool $won): void
    {
        $status = $won ? 'You won!' : 'Better luck next time.';

        $this->notify(
            $user,
            NotificationType::MATCH_RESULT_CONFIRMED,
            'Match Result Confirmed',
            "Your match has been completed. {$status}",
            "/player/matches/{$match->id}",
            [
                'match_id' => $match->id,
                'won' => $won,
            ]
        );
    }

    /**
     * Send match disputed notification.
     */
    public function sendMatchDisputed(User $user, GameMatch $match, string $disputerName): void
    {
        $this->notify(
            $user,
            NotificationType::MATCH_DISPUTED,
            'Match Result Disputed',
            "{$disputerName} has disputed the match result. Organizer will review.",
            "/player/matches/{$match->id}",
            ['match_id' => $match->id]
        );
    }

    /**
     * Send dispute resolved notification.
     */
    public function sendMatchResolved(User $user, GameMatch $match, bool $won): void
    {
        $status = $won ? 'You won!' : 'Better luck next time.';

        $this->notify(
            $user,
            NotificationType::MATCH_RESOLVED,
            'Dispute Resolved',
            "The dispute has been resolved by the organizer. {$status}",
            "/player/matches/{$match->id}",
            [
                'match_id' => $match->id,
                'won' => $won,
            ]
        );
    }

    /**
     * Send no-show reported notification.
     */
    public function sendNoShowReported(User $user, GameMatch $match): void
    {
        $this->notify(
            $user,
            NotificationType::MATCH_NO_SHOW_REPORTED,
            'No-Show Reported Against You',
            'Your opponent has reported you as a no-show. Please respond or contact the organizer.',
            "/player/matches/{$match->id}",
            ['match_id' => $match->id]
        );
    }

    /**
     * Send match expired notification.
     */
    public function sendMatchExpired(User $user, GameMatch $match): void
    {
        $this->notify(
            $user,
            NotificationType::MATCH_EXPIRED,
            'Match Expired - Eliminated',
            'Your match deadline has passed. Both players have been eliminated.',
            "/tournaments/{$match->tournament_id}",
            ['match_id' => $match->id, 'tournament_id' => $match->tournament_id]
        );
    }

    /**
     * Send walkover notification.
     */
    public function sendWalkover(User $user, GameMatch $match, bool $won): void
    {
        $title = $won ? 'Walkover Victory' : 'Walkover Loss';
        $message = $won
            ? 'You have been awarded a walkover victory.'
            : 'Your opponent was awarded a walkover.';

        $this->notify(
            $user,
            NotificationType::MATCH_WALKOVER,
            $title,
            $message,
            "/player/matches/{$match->id}",
            [
                'match_id' => $match->id,
                'won' => $won,
            ]
        );
    }

    /**
     * Send advanced to next round notification.
     */
    public function sendAdvancedToNextRound(User $user, Tournament $tournament, int $roundNumber): void
    {
        $this->notify(
            $user,
            NotificationType::TOURNAMENT_ADVANCED,
            'Advanced to Next Round!',
            "Congratulations! You've advanced to Round {$roundNumber} in {$tournament->name}.",
            "/tournaments/{$tournament->id}",
            ['tournament_id' => $tournament->id, 'round' => $roundNumber]
        );
    }

    /**
     * Send elimination notification.
     */
    public function sendEliminated(User $user, Tournament $tournament, int $position): void
    {
        $this->notify(
            $user,
            NotificationType::TOURNAMENT_ELIMINATED,
            'Tournament Finished',
            "You finished in position #{$position} in {$tournament->name}. Thanks for playing!",
            "/tournaments/{$tournament->id}",
            ['tournament_id' => $tournament->id, 'position' => $position]
        );
    }

    /**
     * Send tournament completed notification.
     */
    public function sendTournamentCompleted(User $user, Tournament $tournament, int $position): void
    {
        $message = $position === 1
            ? "Congratulations! You won {$tournament->name}!"
            : "You finished in position #{$position} in {$tournament->name}.";

        $this->notify(
            $user,
            NotificationType::TOURNAMENT_COMPLETED,
            'Tournament Completed',
            $message,
            "/tournaments/{$tournament->id}",
            ['tournament_id' => $tournament->id, 'position' => $position]
        );
    }

    /**
     * Get opponent name for a user in a match.
     */
    protected function getOpponentName(User $user, GameMatch $match): string
    {
        $userProfileId = $user->playerProfile?->id;

        $match->loadMissing(['player1.playerProfile', 'player2.playerProfile']);

        if ($match->player1?->player_profile_id === $userProfileId) {
            return $match->player2?->playerProfile?->display_name ?? 'TBD';
        }

        return $match->player1?->playerProfile?->display_name ?? 'TBD';
    }

    /**
     * Notify both players in a match.
     */
    public function notifyMatchPlayers(GameMatch $match, callable $callback): void
    {
        $match->loadMissing(['player1.playerProfile.user', 'player2.playerProfile.user']);

        if ($match->player1?->playerProfile?->user) {
            $callback($match->player1->playerProfile->user, $match);
        }

        if ($match->player2?->playerProfile?->user) {
            $callback($match->player2->playerProfile->user, $match);
        }
    }
}
