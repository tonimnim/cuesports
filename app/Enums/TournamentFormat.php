<?php

namespace App\Enums;

enum TournamentFormat: string
{
    case KNOCKOUT = 'knockout';
    case GROUPS_KNOCKOUT = 'groups_knockout';

    public function label(): string
    {
        return match ($this) {
            self::KNOCKOUT => 'Single Elimination Knockout',
            self::GROUPS_KNOCKOUT => 'Group Stage + Knockout',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::KNOCKOUT => 'Players compete in brackets, losers are eliminated',
            self::GROUPS_KNOCKOUT => 'Players divided into groups, top players advance to knockout stage',
        };
    }

    public function hasGroupStage(): bool
    {
        return $this === self::GROUPS_KNOCKOUT;
    }
}
