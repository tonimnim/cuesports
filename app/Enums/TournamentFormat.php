<?php

namespace App\Enums;

enum TournamentFormat: string
{
    case KNOCKOUT = 'knockout';

    public function label(): string
    {
        return 'Single Elimination Knockout';
    }

    public function description(): string
    {
        return 'Players compete in brackets - lose once and you are eliminated. Each match is a "Race to X" series.';
    }
}
