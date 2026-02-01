<?php

namespace App\Enums;

enum ArticleCategory: string
{
    case TOURNAMENTS = 'tournaments';
    case PLAYERS = 'players';
    case ANNOUNCEMENTS = 'announcements';
    case ANALYSIS = 'analysis';
    case GUIDES = 'guides';

    public function label(): string
    {
        return match ($this) {
            self::TOURNAMENTS => 'Tournaments',
            self::PLAYERS => 'Players',
            self::ANNOUNCEMENTS => 'Announcements',
            self::ANALYSIS => 'Analysis',
            self::GUIDES => 'Guides',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TOURNAMENTS => 'primary',
            self::PLAYERS => 'gold',
            self::ANNOUNCEMENTS => 'green',
            self::ANALYSIS => 'purple',
            self::GUIDES => 'blue',
        };
    }
}
