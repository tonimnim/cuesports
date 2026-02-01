<?php

namespace App\Enums;

enum NotificationType: string
{
    // Match notifications
    case MATCH_SCHEDULED = 'match_scheduled';
    case MATCH_REMINDER_48H = 'match_reminder_48h';
    case MATCH_REMINDER_24H = 'match_reminder_24h';
    case MATCH_RESULT_SUBMITTED = 'match_result_submitted';
    case MATCH_RESULT_CONFIRMED = 'match_result_confirmed';
    case MATCH_DISPUTED = 'match_disputed';
    case MATCH_RESOLVED = 'match_resolved';
    case MATCH_EXPIRED = 'match_expired';
    case MATCH_NO_SHOW_REPORTED = 'match_no_show_reported';
    case MATCH_WALKOVER = 'match_walkover';

    // Tournament notifications
    case TOURNAMENT_STARTED = 'tournament_started';
    case TOURNAMENT_ROUND_GENERATED = 'tournament_round_generated';
    case TOURNAMENT_COMPLETED = 'tournament_completed';
    case TOURNAMENT_ELIMINATED = 'tournament_eliminated';
    case TOURNAMENT_ADVANCED = 'tournament_advanced';

    public function label(): string
    {
        return match($this) {
            self::MATCH_SCHEDULED => 'Match Scheduled',
            self::MATCH_REMINDER_48H => '48 Hour Reminder',
            self::MATCH_REMINDER_24H => '24 Hour Reminder',
            self::MATCH_RESULT_SUBMITTED => 'Result Submitted',
            self::MATCH_RESULT_CONFIRMED => 'Result Confirmed',
            self::MATCH_DISPUTED => 'Match Disputed',
            self::MATCH_RESOLVED => 'Dispute Resolved',
            self::MATCH_EXPIRED => 'Match Expired',
            self::MATCH_NO_SHOW_REPORTED => 'No-Show Reported',
            self::MATCH_WALKOVER => 'Walkover Awarded',
            self::TOURNAMENT_STARTED => 'Tournament Started',
            self::TOURNAMENT_ROUND_GENERATED => 'New Round',
            self::TOURNAMENT_COMPLETED => 'Tournament Completed',
            self::TOURNAMENT_ELIMINATED => 'Eliminated',
            self::TOURNAMENT_ADVANCED => 'Advanced to Next Round',
        };
    }
}
